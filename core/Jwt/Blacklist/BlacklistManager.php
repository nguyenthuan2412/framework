<?php

namespace Ryra\Core\Jwt\Blacklist;

use Ryra\Core\Contracts\Container\Container;
use Ryra\Core\Jwt\Exception\JWTException;

class BlacklistManager
{
    private array $drivers = [];

    private string $defaultDriver = 'redis';

    public function __construct(private Container $container) {}

    public function driver(?string $name = null): BlacklistInterface
    {
        $name ??= $this->defaultDriver;

        if (!isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->resolve($name);
        }

        return $this->drivers[$name];
    }

    public function setDefaultDriver(string $name): void
    {
        $this->defaultDriver = $name;
    }

    public function extend(string $name, BlacklistInterface $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    private function resolve(string $name): BlacklistInterface
    {
        return match ($name) {
            'redis'    => $this->createRedisDriver(),
            'database' => $this->createDatabaseDriver(),
            'file'     => $this->createFileDriver(),
            default    => throw new JWTException("Blacklist driver [{$name}] not supported."),
        };
    }

    private function createRedisDriver(): RedisBlacklist
    {
        $connection = env('JWT_REDIS_CONNECTION', 'default');
        return new RedisBlacklist(
            $this->container->get('redis')->connection($connection)
        );
    }

    private function createDatabaseDriver(): DatabaseBlacklist
    {
        $connection = env('JWT_DB_CONNECTION', null);
        $table = env('JWT_BLACKLIST_TABLE', 'jwt_blacklist');
        return new DatabaseBlacklist(
            $this->container->get('database')->getConnection($connection),
            $table,
        );
    }

    private function createFileDriver(): FileBlacklist
    {
        $path = env('JWT_BLACKLIST_PATH', storage_path('jwt'));
        return new FileBlacklist($path);
    }
}
