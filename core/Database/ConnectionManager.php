<?php

namespace Ryra\Core\Database;

use Ryra\Core\Database\Exception\ConnectionNotFoundException;
use Ryra\Core\Contracts\Database\Connection;
use Ryra\Core\Contracts\Pool\PoolManager;
use Ryra\Core\Coroutine\Coroutine;
use Ryra\Core\Log\Log;

class ConnectionManager implements \Ryra\Core\Contracts\Database\ConnectionManager
{
    protected array $connections = [];

    protected array $configs = [];

    protected ?string $defaultConnection = null;

    public function __construct(protected PoolManager $poolManager) {}

    public function addConnection(string $name, array $config): void
    {
        $this->configs[$name] = $config;
    }

    public function getConnection(?string $name = null): Connection
    {
        $name = \is_null($name) ? $this->defaultConnection : $name;
        $config = $this->getConfig($name);
        // Ngoài coroutine (CLI, messenger:consume) không dùng pool được: release()
        // phải đi qua Coroutine::defer(), mà defer() ngoài coroutine ném Swoole\Error
        // - là \Error nên lọt hết mọi catch. Ở đó dùng connection thường, giữ nguyên
        // cho cả process.
        if (
            !empty($config['pool_options'])
            && Coroutine::id() > 0
            && !$this->hasConnectionInCoroutine($name)
        ) {
            $connection = $this->getConnectionInPool($name);
            $this->setConnectionInCoroutine($name, $connection);
            return $connection;
        }

        return $this->getConnectionInCoroutine($name);
    }

    public function getConfig(?string $name = null): ?array
    {
        $name = \is_null($name) ? $this->defaultConnection : $name;
        return $this->configs[$name] ?? null;
    }

    public function setDefaultConnection(string $defaultConnection): static
    {
        $this->defaultConnection = $defaultConnection;
        return $this;
    }

    public function getDefaultConnection(): ?string
    {
        return $this->defaultConnection;
    }

    public function disconnect(): void
    {
        $cid = Coroutine::id();
        if (!empty($this->connections[$cid])) {
            foreach ($this->connections[$cid] as $connection) {
                $connection->disconnect();
            }
            unset($this->connections[$cid]);
        }
    }

    public function release(): void
    {
        $cid = Coroutine::id();
        if (empty($this->connections[$cid])) {
            return;
        }

        try {
            foreach ($this->connections[$cid] as $name => $connection) {
                try {
                    $this->poolManager->get("_db:$name")?->release($connection);
                } catch (\Throwable $throwable) {
                    Log::error((string) $throwable);
                }
            }
        } finally {
            // Phải chạy kể cả khi release lỗi, nếu không mảng này phình theo từng
            // coroutine id và không bao giờ được dọn.
            unset($this->connections[$cid]);
        }
    }

    protected function getConnectionInPool(string $name): Connection
    {
        if (!$this->poolManager->has("_db:$name")) {
            $config = $this->getConfig($name);
            $poolFactory = new ConnectionFactory($config);
            $this->poolManager->register("_db:$name", $poolFactory, $config['pool_options']);
        }
        
        $connection = $this->poolManager->get("_db:$name")->get();
        Coroutine::defer([$this, 'release']);
        return $connection;
    }

    protected function hasConnectionInCoroutine(string $name): bool
    {
        return isset($this->connections[Coroutine::id()][$name]);
    }

    protected function setConnectionInCoroutine(string $name, Connection $connection): void
    {
        $this->connections[Coroutine::id()][$name] = $connection;
    }

    protected function getConnectionInCoroutine(string $name): Connection
    {
        if ($this->hasConnectionInCoroutine($name)) {
            return $this->connections[Coroutine::id()][$name];
        }

        if (isset($this->configs[$name])) {
            $connection = new \Ryra\Core\Database\Connection($this->configs[$name]);
            $this->setConnectionInCoroutine($name, $connection);
            if (Coroutine::id() > 0) {
                Coroutine::defer([$this, 'disconnect']);
            }
            return $connection;
        }

        throw new ConnectionNotFoundException("Database connection configuration for '$name' not found.");
    }
}