<?php

namespace Ryra\Core\Database;

use Ryra\Core\ErrorHandler\Exception\DumperException;
use Ryra\Core\Database\Query\PostgreBuilder;
use Ryra\Core\Database\Query\MysqlBuilder;
use Ryra\Core\Database\Query\Builder;
use Ryra\Core\Support\Str;
use Ryra\Core\Log\Log;
use PDOStatement;
use UnitEnum;
use PDO;

class Connection implements \Ryra\Core\Contracts\Database\Connection
{
    protected string $query = '';

    protected array $params = [];

    protected ?PDO $pdo = null;

    protected ?string $prefix = null;

    protected ?int $lastQueryTime = null;

    protected int $transactions = 0;

    public function __construct(protected array $config)
    {
        $this->connect();
    }

    public function select(string $query, array $bindings = [], int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->statement($query, $bindings)->fetchAll($mode, ...$args);
    }

    public function selectOne(string $query, array $bindings = [], int $mode = PDO::FETCH_DEFAULT, mixed ...$args): mixed
    {
        $result = $this->select($query, $bindings, $mode, ...$args);
        return \array_shift($result);
    }

    public function insert(string $query, array $bindings = []): int
    {
        return $this->statement($query, $bindings)->rowCount();
    }

    public function insertGetId(string $query, string $name, array $bindings = []): string|false
    {
        $this->statement($query, $bindings);
        return $this->lastInsertId($name);
    }

    public function delete(string $query, array $bindings = []): int
    {
        return $this->statement($query, $bindings)->rowCount();
    }

    public function update(string $query, array $bindings = []): int
    {
        return $this->statement($query, $bindings)->rowCount();
    }

    public function statement(string $query, array $bindings = []): PDOStatement|false
    {
        return $this->runWithReconnect(function () use ($query, $bindings) {
            $statement = $this->pdo->prepare($query);
            $this->bindValues($statement, $bindings);
            $statement->execute();
            $this->lastQueryTime = \time();
            return $statement;
        });
    }

    /**
     * Chạy một thao tác PDO, thử kết nối lại đúng một lần nếu connection đã chết.
     *
     * Chỉ chạy lại khi reconnect() thực sự thành công. Nếu bỏ qua giá trị trả về
     * của reconnect() thì $this->pdo vẫn là null và lần gọi kế tiếp ném \Error chứ
     * không phải PDOException - lọt ra ngoài mọi catch, rồi connection null đó bị
     * đẩy ngược vào pool.
     */
    protected function runWithReconnect(\Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (\PDOException $e) {
            if (!$this->causedByLostConnection($e)) {
                throw $e;
            }

            // Mất connection giữa chừng transaction thì mọi thứ đã ghi đều bay theo.
            // Không được âm thầm chạy lại query ngoài transaction - trả lỗi lên cho
            // caller rollback, ở đây chỉ dọn state để connection còn dùng lại được.
            if ($this->transactions > 0) {
                $this->transactions = 0;
                $this->reconnect();
                throw $e;
            }

            if (!$this->reconnect()) {
                throw $e;
            }
        }

        return $callback();
    }

    public function transaction(callable $callback, int $retries = 1): mixed
    {
        for ($i = 1; $i <= $retries; $i++) { 
            try {
                $this->beginTransaction();
                $result = $callback();
            } catch (\Exception $e) {
                $this->rollBack();

                if ($e instanceof DumperException) {
                    \dd(...$e->vars);
                }

                if ($i === $retries) {
                    throw $e;
                }

                continue;
            }

            if ($this->transactions === 1) {
                if ($this->inTransaction()) {
                    $this->commit();
                } else {
                    // Server đã tự kết thúc transaction (DDL gây implicit commit,
                    // hoặc connection vừa được dựng lại). Phải đưa bộ đếm về 0, nếu
                    // không connection này quay lại pool với transactions === 1 và
                    // người mượn kế tiếp sẽ tạo savepoint trên transaction không có
                    // thật.
                    $this->transactions = 0;
                }
            } else {
                $this->transactions = \max(0, $this->transactions - 1);
            }

            return $result;
        }

        return false;
    }

    public function beginTransaction(): bool
    {
        if ($this->transactions === 0) {
            // Đây là lệnh đầu tiên chạm tới connection sau khi lấy từ pool, nên nó
            // mới là chỗ phát hiện connection đã chết chứ không phải statement().
            $transactionCreated = $this->runWithReconnect(fn () => $this->pdo->beginTransaction());
        } else {
            $this->pdo->exec("savepoint trans{$this->transactions}");
            $transactionCreated = true;
        }

        if ($transactionCreated) {
            $this->transactions++;
            return true;
        }

        return false;
    }

    public function commit(): bool
    {
        $this->transactions = 0;

        // Không trả false lặng lẽ ở đây: caller sẽ tưởng đã ghi xong trong khi
        // connection đứt giữa transaction đồng nghĩa server đã rollback sạch.
        if (!$this->isConnected()) {
            throw new \RuntimeException('Cannot commit: the database connection was lost during the transaction.');
        }

        return $this->pdo->commit();
    }

    public function rollBack(bool $rollbackAll = false): bool
    {
        // Không có transaction
        if ($this->transactions === 0) {
            return false;
        }

        // Rollback toàn bộ hoặc rollback level 1
        if ($rollbackAll || $this->transactions === 1) {
            if ($this->inTransaction()) {
                $this->transactions = 0;
                return $this->pdo->rollBack();
            }

            $this->transactions = 0;
            return false;
        }

        // Rollback savepoint
        $this->transactions--;
        if ($this->inTransaction()) {
            $this->pdo->exec("rollback to savepoint trans{$this->transactions}");
        }

        return true;
    }

    public function inTransaction(): bool
    {
        return $this->isConnected() && $this->pdo->inTransaction();
    }

    /**
     * Connection còn giữ được PDO handle hay không.
     *
     * disconnect() và reconnect() thất bại đều để lại $this->pdo === null, mọi chỗ
     * chạm tới $this->pdo phải đi qua đây nếu không muốn nhận \Error thay vì
     * PDOException.
     */
    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    public function bindValues(PDOStatement $statement, array $bindings): static
    {
        foreach ($bindings as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s.u');
            } elseif (\is_bool($value)) {
                $value = (int) $value;
            } elseif ($value instanceof UnitEnum) {
                $value = $value->value;
            }

            $statement->bindValue(
                \is_string($key) ? $key : $key + 1,
                $value,
                match (true) {
                    \is_int($value) => PDO::PARAM_INT,
                    \is_resource($value) => PDO::PARAM_LOB,
                    \is_null($value) => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR,
                }
            );
        }
        return $this;
    }

    // getAttribute/setAttribute có thể là lệnh đầu tiên chạm connection sau khi lấy
    // từ pool (xem DatabaseBlacklist đọc ATTR_DRIVER_NAME), và cả hai đều lặp lại
    // được nên cho đi qua lớp reconnect luôn.
    public function getAttribute(int $attribute): mixed
    {
        return $this->runWithReconnect(fn () => $this->pdo->getAttribute($attribute));
    }

    public function setAttribute(int $attribute, mixed $value): static
    {
        $this->runWithReconnect(fn () => $this->pdo->setAttribute($attribute, $value));
        return $this;
    }

    public function lastInsertId(string $name): string|false
    {
        return $this->runWithReconnect(fn () => $this->pdo->lastInsertId($name));
    }

    public function ping(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        try {
            $this->pdo->query('select 1');
            return true;
        } catch (\Exception $e) {
            Log::error((string) $e);
        }

        return false;
    }

    public function reconnect(int $retries = 3): bool
    {
        for ($i = 0; $i < $retries; $i++) { 
            try {
                $this->disconnect();
                $this->connect();
                return true;
            } catch (\Throwable) {
                if ($i == $retries - 1) {
                    return false;
                }
            }
        }

        return false;
    }

    public function disconnect(): void
    {
        $this->pdo = null;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function getLastQueryTime(): int
    {
        return $this->lastQueryTime;
    }

    public function config(): array
    {
        return $this->config;
    }

    public function query(): Builder
    {
        $driver = $this->config['driver'];
        return match ($driver) {
            'mysql' => new MysqlBuilder($this),
            'postgres' => new PostgreBuilder($this),
            'sqlite' => new MysqlBuilder($this)
        };
    }

    protected function connect(): void
    {
        $config = &$this->config;
        $this->pdo = new PDO(
            $this->getDsn($config),
            $config['username'] ?: null,
            $config['password'] ?: null,
            $config['options'] ?: null
        );

        if (!empty($config['database'])) {
            $this->pdo->exec("use `{$config['database']}`;");
        }
        if (!empty($config['prefix'])) {
            $this->prefix = $config['prefix'];
        }

        // Phiên cũ đã chết kéo theo mọi transaction đang mở, bộ đếm phải về 0 nếu
        // không connection mới sẽ tưởng mình đang ở trong transaction và đi tạo
        // savepoint trên một transaction không tồn tại.
        $this->transactions = 0;
        $this->lastQueryTime = time();
    }

    protected function getDsn(array $config): string
    {
        return $this->hasSocket($config)
            ? $this->getSocketDsn($config) : $this->getHostDsn($config);
    }

    protected function hasSocket(array $config): bool
    {
        return !empty($config['unix_socket']);
    }

    protected function getSocketDsn(array $config): string
    {
        return "{$config['driver']}:unix_socket={$config['unix_socket']};dbname={$config['database']}";
    }

    protected function getHostDsn(array $config): string
    {
        \extract($config, EXTR_SKIP);
        return !empty($port)
            ? "$driver:dbname=$database;host=$host;port=$port;"
            : "$driver:dbname=$database;host=$host;";
    }

    /**
     * Determine if the given exception was caused by a lost connection.
     */
    protected function causedByLostConnection(\PDOException $e): bool
    {
        $code = $e->errorInfo[1];
        if (in_array($code, [2002, 2006, 4031])) {
            return true;
        }

        $message = $e->getMessage();
        return Str::contains($message, [
            'server has gone away',
            'Server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'server closed the connection unexpectedly',
            'SSL connection has been closed unexpectedly',
            'Error writing data to the connection',
            'Resource deadlock avoided',
            'Transaction() on null',
            'child connection forced to terminate due to client_idle_limit',
            'query_wait_timeout',
            'reset by peer',
            'Physical connection is not usable',
            'TCP Provider: Error code 0x68',
            'ORA-03114',
            'Packets out of order. Expected',
            'Adaptive Server connection failed',
            'Communication link failure',
            'connection is no longer usable',
            'Login timeout expired',
            'SQLSTATE[HY000] [2002] Connection refused',
            'running with the --read-only option so it cannot execute this statement',
            'The connection is broken and recovery is not possible. The connection is marked by the client driver as unrecoverable. No attempt was made to restore the connection.',
            'SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed: Try again',
            'SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed: Name or service not known',
            'SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo for',
            'SQLSTATE[HY000]: General error: 7 SSL SYSCALL error: EOF detected',
            'SQLSTATE[HY000] [2002] Connection timed out',
            'SSL: Connection timed out',
            'SQLSTATE[HY000]: General error: 1105 The last transaction was aborted due to Seamless Scaling. Please retry.',
            'Temporary failure in name resolution',
            'SSL: Broken pipe',
            'SQLSTATE[08S01]: Communication link failure',
            'SQLSTATE[08006] [7] could not connect to server: Connection refused Is the server running on host',
            'SQLSTATE[HY000]: General error: 7 SSL SYSCALL error: No route to host',
            'The client was disconnected by the server because of inactivity. See wait_timeout and interactive_timeout for configuring this behavior.',
            'SQLSTATE[08006] [7] could not translate host name',
            'TCP Provider: Error code 0x274C',
            'SQLSTATE[HY000] [2002] No such file or directory',
            'SSL: Operation timed out',
            'Reason: Server is in script upgrade mode. Only administrator can connect at this time.',
            'Unknown $curl_error_code: 77',
            'SSL: Handshake timed out',
            'SQLSTATE[08006] [7] SSL error: sslv3 alert unexpected message',
            'SQLSTATE[08006] [7] unrecognized SSL error code:',
            'SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it',
            'SQLSTATE[HY000] [2002] A connection attempt failed because the connected party did not properly respond after a period of time, or established connection failed because connected host has failed to respond',
            'SQLSTATE[HY000] [2002] Network is unreachable',
            'SQLSTATE[HY000] [2002] The requested address is not valid in its context',
            'SQLSTATE[HY000] [2002] A socket operation was attempted to an unreachable network',
            'SQLSTATE[HY000]: General error: 3989',
            'went away',
            'No such file or directory',
            'server is shutting down',
            'failed to connect to',
        ]);
    }
}