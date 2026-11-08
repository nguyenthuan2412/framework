<?php

namespace Ryra\Core\Redis;

use Ryra\Core\Redis\Connections\Connection;
use Ryra\Core\Redis\Connectors\ConnectorInterface;

class RedisConnectionFactory implements \Ryra\Core\Contracts\Pool\ConnectionFactory
{
    public function __construct(
        protected ConnectorInterface $connector,
        protected array $config
    ) {}

    /**
     * Tạo Redis connection mới.
     */
    public function create(): Connection
    {
        return $this->connector->connect($this->config);
    }

    /**
     * Hủy connection.
     */
    public function destroy(object $client): void
    {
        if ($client instanceof Connection) {
            $client->disconnect();
        }
    }

    /**
     * Kiểm tra connection còn sống không.
     */
    public function ping(object $client): bool
    {
        if ($client instanceof Connection) {
            return $client->ping();
        }
        return false;
    }

    /**
     * Reset connection state.
     */
    public function reset(object $client): void
    {
        // Redis không cần reset như database transaction
        // Có thể select lại database nếu cần
        if ($client instanceof Connection && isset($this->config['database']) && $this->config['database'] !== 0) {
            $client->select($this->config['database']);
        }
    }

    /**
     * Lấy thời gian từ lần query cuối.
     */
    public function getLastQueryTime(object $client): int
    {
        if ($client instanceof Connection && method_exists($client, 'getLastQueryTime')) {
            return $client->getLastQueryTime();
        }
        return 0;
    }
}
