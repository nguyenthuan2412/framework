<?php

namespace Ryra\Core\Redis\Connectors;

use Predis\Client;
use Predis\Connection\ConnectionException;
use RuntimeException;
use Ryra\Core\Redis\Connections\Connection;
use Ryra\Core\Redis\Connections\PredisConnection;

class PredisConnector implements ConnectorInterface
{
    /**
     * Tạo kết nối Redis sử dụng Predis library.
     */
    public function connect(array $config): Connection
    {
        if (!class_exists(Client::class)) {
            throw new RuntimeException('Predis library is not installed. Run: composer require predis/predis');
        }

        $parameters = $this->buildParameters($config);
        $options = $this->buildOptions($config);

        try {
            $client = new Client($parameters, $options);
            $client->connect();
            return new PredisConnection($client);
        } catch (ConnectionException $e) {
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 6379;
            throw new RuntimeException("Could not connect to Redis at $host:$port - " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Build connection parameters.
     */
    protected function buildParameters(array $config): array
    {
        $parameters = [
            'scheme' => $config['scheme'] ?? 'tcp',
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => $config['port'] ?? 6379,
            'database' => $config['database'] ?? 0,
            'timeout' => $config['timeout'] ?? 0.0,
            'read_write_timeout' => $config['read_timeout'] ?? 60.0,
        ];

        if (isset($config['password']) && $config['password'] !== null && $config['password'] !== '') {
            $parameters['password'] = $config['password'];
        }

        if (isset($config['username']) && $config['username'] !== null && $config['username'] !== '') {
            $parameters['username'] = $config['username'];
        }

        if (isset($config['persistent']) && $config['persistent']) {
            $parameters['persistent'] = true;
        }

        return $parameters;
    }

    /**
     * Build client options.
     */
    protected function buildOptions(array $config): array
    {
        $options = [];

        if (isset($config['prefix']) && $config['prefix'] !== '') {
            $options['prefix'] = $config['prefix'];
        }

        return $options;
    }
}
