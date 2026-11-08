<?php

namespace Ryra\Core\HttpClient;

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
        if ($client->inTransaction()) {
            $client->rollBack(true);
        }
    }

    public function getLastQueryTime(object $client): int
    {
        return $client->getLastQueryTime();
    }
}
