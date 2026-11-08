<?php

namespace Ryra\Core\Cache;

use Ryra\Core\Contracts\Database\ConnectionManager;
use Ryra\Core\Contracts\Container\Container;
use Ryra\Core\Cache\Drivers\DatabaseDriver;
use Ryra\Core\Contracts\Config\Repository;
use Ryra\Core\Cache\Drivers\RedisDriver;
use Ryra\Core\Cache\Drivers\FileDriver;
use Ryra\Core\Support\Manager;
use Carbon\Traits\Macro;

class CacheManager extends Manager
{
    use Macro;

    protected Repository $config;

    protected ConnectionManager $database;

    protected ?string $prefix = null;

    public function __construct(protected Container $container)
    {
        $this->config = $this->container->get('config');
        $this->database = $this->container->get('database');
        $this->prefix = $this->config->get('cache.prefix');
    }

    public function getDefaultDriver(): string
    {
        return \config('cache.default', 'file');
    }

    public function createDatabaseDriver()
    {
        $dataBaseConfig = $this->config->get('cache.stores.database');
        $connection = $this->database->getConnection($dataBaseConfig['connection']);
        $marshaller = $this->container::make($dataBaseConfig['marshaller']);
        return new DatabaseDriver(
            $connection,
            $dataBaseConfig['table'],
            $marshaller,
            $this->prefix,
        );
    }

    public function createFileDriver()
    {
        $fileConfig = $this->config->get('cache.stores.file');
        $marshaller = $this->container::make($fileConfig['marshaller']);
        return new FileDriver(
            $fileConfig['path'],
            $marshaller,
            $this->prefix,
        );
    }

    public function createRedisDriver(): RedisDriver
    {
        $redisConfig = $this->config->get('cache.stores.redis');
        $redis = $this->container->get('redis');
        $marshaller = $this->container::make($redisConfig['marshaller']);

        return new RedisDriver(
            $redis,
            $redisConfig['connection'] ?? 'cache',
            $marshaller,
            $this->prefix,
        );
    }
}