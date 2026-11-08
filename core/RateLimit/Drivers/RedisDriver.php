<?php

namespace Ryra\Core\RateLimit\Drivers;

use Ryra\Core\RateLimit\Contracts\RateLimiterDriverInterface;
use Ryra\Core\Redis\RedisManager;

/**
 * Redis driver — production-ready, atomic via Lua scripts.
 * Best for: distributed systems, high traffic.
 */
class RedisDriver implements RateLimiterDriverInterface
{
    /**
     * Lua: atomic INCR + EXPIRE on first write.
     * Returns new counter value.
     */
    private const LUA_INCREMENT = <<<'LUA'
local current = redis.call('INCR', KEYS[1])
if current == 1 then
    redis.call('EXPIRE', KEYS[1], tonumber(ARGV[1]))
end
return current
LUA;

    public function __construct(
        private RedisManager $redis,
        private string $connection = 'cache',
        private string $prefix = 'rl:',
    ) {}

    private function conn(): object
    {
        return $this->redis->connection($this->connection);
    }

    private function key(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * {@inheritdoc}
     * Uses Lua script for atomic INCR + EXPIRE.
     */
    public function increment(string $key, int $ttl): int
    {
        return (int) $this->conn()->eval(
            self::LUA_INCREMENT,
            [$this->key($key)],
            1,
            (string) $ttl,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getData(string $key): ?array
    {
        $value = $this->conn()->get($this->key($key));

        if ($value === false || $value === null) {
            return null;
        }

        return json_decode($value, true);
    }

    /**
     * {@inheritdoc}
     */
    public function setData(string $key, array $data, int $ttl): bool
    {
        return (bool) $this->conn()->setex(
            $this->key($key),
            $ttl,
            json_encode($data),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function reset(string $key): bool
    {
        return (bool) $this->conn()->del($this->key($key));
    }

    /**
     * {@inheritdoc}
     */
    public function ttl(string $key): int
    {
        return (int) $this->conn()->ttl($this->key($key));
    }
}
