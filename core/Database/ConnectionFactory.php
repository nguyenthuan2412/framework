<?php

namespace Ryra\Core\Database;

class ConnectionFactory implements \Ryra\Core\Contracts\Pool\ConnectionFactory
{
    public function __construct(protected array $config) {}

    public function create(): object
    {
        return new Connection($this->config);
    }

    public function destroy(object $client): void
    {
        $client->disconnect();
    }

    public function ping(object $client): bool
    {
        return $client->ping();
    }

    public function reset(object $client): void
    {
        // Ném ở đây là cách báo cho Pool::release() biết connection này không được
        // đẩy lại vào channel. Pool sẽ destroy nó và trả slot, lần get() kế tiếp
        // dựng connection mới.
        if (!$client->isConnected()) {
            throw new \RuntimeException('Database connection is no longer usable and will not be returned to the pool.');
        }

        if ($client->inTransaction()) {
            $client->rollBack(true);
        }
    }

    public function getLastQueryTime(object $client): int
    {
        return $client->getLastQueryTime();
    }
}
