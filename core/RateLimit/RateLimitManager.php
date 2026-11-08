<?php

namespace Ryra\Core\RateLimit;

use Ryra\Core\Contracts\Container\Container;
use Ryra\Core\Contracts\Database\ConnectionManager;
use Ryra\Core\RateLimit\Contracts\RateLimiterDriverInterface;
use Ryra\Core\RateLimit\Drivers\DatabaseDriver;
use Ryra\Core\RateLimit\Drivers\FileDriver;
use Ryra\Core\RateLimit\Drivers\MemoryDriver;
use Ryra\Core\RateLimit\Drivers\RedisDriver;

/**
 * Manager để tạo và cache RateLimiter instances theo driver.
 *
 * Đăng ký vào container:
 *   $app->singleton('rate_limit', fn($app) => new RateLimitManager($app));
 */
class RateLimitManager
{
    private array $limiters = [];

    public function __construct(private Container $container) {}

    /**
     * Get RateLimiter for a given driver.
     *
     * @param string $name redis | database | file | memory | default
     */
    public function driver(string $name = 'default'): RateLimiter
    {
        if ($name === 'default') {
            $name = config('rate_limit.default', 'redis');
        }

        if (!isset($this->limiters[$name])) {
            $this->limiters[$name] = new RateLimiter($this->createDriver($name));
        }

        return $this->limiters[$name];
    }

    private function createDriver(string $name): RateLimiterDriverInterface
    {
        $prefix = config('rate_limit.prefix', 'rl:');

        return match ($name) {
            'redis'    => $this->createRedisDriver($prefix),
            'database' => $this->createDatabaseDriver($prefix),
            'file'     => $this->createFileDriver($prefix),
            'memory'   => new MemoryDriver($prefix),
            default    => throw new \InvalidArgumentException("Unknown rate limit driver: [{$name}]"),
        };
    }

    private function createRedisDriver(string $prefix): RedisDriver
    {
        $connection = config('rate_limit.stores.redis.connection', 'cache');

        return new RedisDriver(
            $this->container->get('redis'),
            $connection,
            $prefix,
        );
    }

    private function createDatabaseDriver(string $prefix): DatabaseDriver
    {
        /** @var ConnectionManager $dbManager */
        $dbManager  = $this->container->get('database');
        $dbConfig   = config('rate_limit.stores.database', []);
        $connection = $dbManager->getConnection($dbConfig['connection'] ?? null);
        $table      = $dbConfig['table'] ?? 'rate_limits';

        return new DatabaseDriver($connection, $table, $prefix);
    }

    private function createFileDriver(string $prefix): FileDriver
    {
        $path = config('rate_limit.stores.file.path', 'storage/rate_limit');

        return new FileDriver($path, $prefix);
    }

    /**
     * Proxy calls to default driver's RateLimiter.
     *
     * RateLimit::attempt(...) → RateLimitManager::attempt(...) → driver()->attempt(...)
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->driver()->{$method}(...$arguments);
    }
}
