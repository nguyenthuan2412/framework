<?php

namespace Ryra\Core\Redis\Connectors;

use Ryra\Core\Redis\Connections\PhpRedisConnection;
use Ryra\Core\Redis\Connections\Connection;
use RuntimeException;
use RedisException;
use Redis;

class PhpRedisConnector implements ConnectorInterface
{
    /**
     * Tạo kết nối Redis sử dụng phpredis extension.
     */
    public function connect(array $config): Connection
    {
        if (!extension_loaded('redis')) {
            throw new RuntimeException('phpredis extension is not installed.');
        }

        $client = new Redis();

        $this->establishConnection($client, $config);
        $this->authenticate($client, $config);
        $this->selectDatabase($client, $config);
        $this->setOptions($client, $config);

        return new PhpRedisConnection($client);
    }

    /**
     * Thiết lập kết nối đến Redis server.
     */
    protected function establishConnection(Redis $client, array $config): void
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $timeout = $config['timeout'] ?? 0.0;
        $readTimeout = $config['read_timeout'] ?? 60.0;
        $persistent = $config['persistent'] ?? false;

        try {
            if ($persistent) {
                $persistentId = $config['persistent_id'] ?? null;
                $client->pconnect($host, $port, $timeout, $persistentId);
            } else {
                $client->connect($host, $port, $timeout);
            }

            if ($readTimeout > 0) {
                $client->setOption(Redis::OPT_READ_TIMEOUT, $readTimeout);
            }
        } catch (RedisException $e) {
            throw new RuntimeException("Could not connect to Redis at $host:$port - " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Xác thực với Redis server.
     */
    protected function authenticate(Redis $client, array $config): void
    {
        $password = $config['password'] ?? null;
        $username = $config['username'] ?? null;

        if ($password !== null && $password !== '') {
            try {
                if ($username !== null && $username !== '') {
                    // Redis 6+ ACL authentication
                    $client->auth([$username, $password]);
                } else {
                    $client->auth($password);
                }
            } catch (RedisException $e) {
                throw new RuntimeException('Redis authentication failed: ' . $e->getMessage(), 0, $e);
            }
        }
    }

    /**
     * Chọn database.
     */
    protected function selectDatabase(Redis $client, array $config): void
    {
        $database = $config['database'] ?? 0;

        if ($database !== 0) {
            $client->select($database);
        }
    }

    /**
     * Thiết lập các options.
     */
    protected function setOptions(Redis $client, array $config): void
    {
        $prefix = $config['prefix'] ?? '';

        if ($prefix !== '') {
            $client->setOption(Redis::OPT_PREFIX, $prefix);
        }

        // Serializer
        if (isset($config['serializer'])) {
            $client->setOption(Redis::OPT_SERIALIZER, $config['serializer']);
        }

        // Scan retry
        $client->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
    }
}
