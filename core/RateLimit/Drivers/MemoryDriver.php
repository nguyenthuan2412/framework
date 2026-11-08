<?php

namespace Ryra\Core\RateLimit\Drivers;

use Ryra\Core\RateLimit\Contracts\RateLimiterDriverInterface;

/**
 * In-memory driver — process-level storage, no persistence.
 * Best for: unit tests, local development, single-process Swoole workers.
 * Warning: resets on process restart; not suitable for multi-process production.
 */
class MemoryDriver implements RateLimiterDriverInterface
{
    private static array $store = [];

    public function __construct(private string $prefix = 'rl:') {}

    private function key(string $key): string
    {
        return $this->prefix . $key;
    }

    private function isExpired(array $entry): bool
    {
        return isset($entry['expires_at'])
            && $entry['expires_at'] > 0
            && $entry['expires_at'] <= time();
    }

    /**
     * {@inheritdoc}
     */
    public function increment(string $key, int $ttl): int
    {
        $fullKey = $this->key($key);

        if (!isset(self::$store[$fullKey]) || $this->isExpired(self::$store[$fullKey])) {
            self::$store[$fullKey] = ['count' => 1, 'expires_at' => time() + $ttl];
            return 1;
        }

        self::$store[$fullKey]['count']++;

        return self::$store[$fullKey]['count'];
    }

    /**
     * {@inheritdoc}
     */
    public function getData(string $key): ?array
    {
        $fullKey = $this->key($key);

        if (!isset(self::$store[$fullKey]) || $this->isExpired(self::$store[$fullKey])) {
            unset(self::$store[$fullKey]);
            return null;
        }

        return self::$store[$fullKey]['payload'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function setData(string $key, array $data, int $ttl): bool
    {
        self::$store[$this->key($key)] = [
            'payload'    => $data,
            'expires_at' => time() + $ttl,
        ];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function reset(string $key): bool
    {
        unset(self::$store[$this->key($key)]);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function ttl(string $key): int
    {
        $fullKey = $this->key($key);

        if (!isset(self::$store[$fullKey])) {
            return -2;
        }

        if ($this->isExpired(self::$store[$fullKey])) {
            unset(self::$store[$fullKey]);
            return -2;
        }

        $expiresAt = self::$store[$fullKey]['expires_at'] ?? 0;

        if ($expiresAt === 0) {
            return -1;
        }

        return max(0, $expiresAt - time());
    }

    /**
     * Clear all in-memory entries (useful in tests).
     */
    public static function flush(): void
    {
        self::$store = [];
    }
}
