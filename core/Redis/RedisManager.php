<?php

namespace Ryra\Core\Redis;

use Ryra\Core\Contracts\Config\Repository;
use Ryra\Core\Coroutine\Coroutine;
use Ryra\Core\Contracts\Pool\PoolManager;
use Ryra\Core\Redis\Connections\Connection;
use Ryra\Core\Redis\Connectors\ConnectorInterface;
use Ryra\Core\Redis\Connectors\PhpRedisConnector;
use Ryra\Core\Redis\Connectors\PredisConnector;
use RuntimeException;

class RedisManager
{
    /** @var array<int, array<string, Connection>> */
    protected array $connections = [];

    protected array $connectors = [
        'phpredis' => PhpRedisConnector::class,
        'predis' => PredisConnector::class,
    ];

    public function __construct(
        protected Repository $config,
        protected PoolManager $poolManager
    ) {}

    /**
     * Lấy Redis connection theo tên.
     */
    public function connection(string $name = 'default'): Connection
    {
        $config = $this->getConnectionConfig($name);

        if (!empty($config['pool_options']) && !$this->hasConnectionInCoroutine($name)) {
            $connection = $this->getConnectionFromPool($name, $config);
            $this->setConnectionInCoroutine($name, $connection);
            return $connection;
        }

        return $this->getConnectionInCoroutine($name, $config);
    }

    /**
     * Lấy tên connection mặc định.
     */
    public function getDefaultConnection(): string
    {
        return $this->config->get('database.redis.default', 'default');
    }

    /**
     * Lấy config của connection.
     */
    public function getConnectionConfig(string $name): array
    {
        $config = $this->config->get("database.redis.$name");

        if ($config === null) {
            throw new RuntimeException("Redis connection [$name] not configured.");
        }

        // Merge với global options
        $options = $this->config->get('database.redis.options', []);

        if (!empty($options['prefix']) && empty($config['prefix'])) {
            $config['prefix'] = $options['prefix'];
        }

        return $config;
    }

    /**
     * Lấy Redis client type (phpredis hoặc predis).
     */
    public function getClient(): string
    {
        return $this->config->get('database.redis.client', 'phpredis');
    }

    /**
     * Tạo connector instance.
     */
    protected function createConnector(): ConnectorInterface
    {
        $client = $this->getClient();

        if (!isset($this->connectors[$client])) {
            throw new RuntimeException("Unsupported Redis client [$client]. Supported: phpredis, predis");
        }

        $connectorClass = $this->connectors[$client];
        return new $connectorClass();
    }

    /**
     * Lấy connection từ pool.
     */
    protected function getConnectionFromPool(string $name, array $config): Connection
    {
        $poolName = "_redis:$name";

        if (!$this->poolManager->has($poolName)) {
            $connector = $this->createConnector();
            $factory = new RedisConnectionFactory($connector, $config);
            $this->poolManager->register($poolName, $factory, $config['pool_options']);
        }

        $connection = $this->poolManager->get($poolName)->get();
        $connection->setName($name);
        if (Coroutine::id() > 0) {
            Coroutine::defer(fn() => $this->release($name));
        }

        return $connection;
    }

    /**
     * Trả connection về pool.
     */
    public function release(?string $name = null): void
    {
        $cid = Coroutine::id();

        if (!empty($this->connections[$cid])) {
            foreach ($this->connections[$cid] as $connName => $connection) {
                if ($name === null || $name === $connName) {
                    $this->poolManager->get("_redis:$connName")->release($connection);
                    unset($this->connections[$cid][$connName]);
                }
            }

            if (empty($this->connections[$cid])) {
                unset($this->connections[$cid]);
            }
        }
    }

    /**
     * Đóng tất cả connections trong coroutine hiện tại.
     */
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

    /**
     * Kiểm tra có connection trong coroutine không.
     */
    protected function hasConnectionInCoroutine(string $name): bool
    {
        return isset($this->connections[Coroutine::id()][$name]);
    }

    /**
     * Lưu connection vào coroutine context.
     */
    protected function setConnectionInCoroutine(string $name, Connection $connection): void
    {
        $this->connections[Coroutine::id()][$name] = $connection;
    }

    /**
     * Lấy hoặc tạo connection trong coroutine.
     */
    protected function getConnectionInCoroutine(string $name, array $config): Connection
    {
        if ($this->hasConnectionInCoroutine($name)) {
            return $this->connections[Coroutine::id()][$name];
        }

        $connector = $this->createConnector();
        $connection = $connector->connect($config);
        $connection->setName($name);
        $this->setConnectionInCoroutine($name, $connection);

        if (Coroutine::id() > 0) {
            Coroutine::defer(fn() => $this->disconnect());
        }

        return $connection;
    }

    /**
     * Tạo dedicated connection cho subscribing (không dùng pool).
     * Connection này sẽ bị block khi subscribe nên cần tạo riêng.
     */
    public function subscriber(string $name = 'default'): Connection
    {
        $config = $this->getConnectionConfig($name);
        $connector = $this->createConnector();
        $connection = $connector->connect($config);
        $connection->setName($name);

        return $connection;
    }

    /**
     * Đăng ký custom connector.
     */
    public function extend(string $name, string $connectorClass): static
    {
        $this->connectors[$name] = $connectorClass;
        return $this;
    }

    /**
     * Proxy method calls đến default connection.
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->connection()->$method(...$arguments);
    }
}
