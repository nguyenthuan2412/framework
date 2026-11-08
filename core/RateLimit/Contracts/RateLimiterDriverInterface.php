<?php

namespace Ryra\Core\RateLimit\Contracts;

interface RateLimiterDriverInterface
{
    /**
     * Atomic increment. Set TTL if key is new.
     * Returns new count after increment.
     */
    public function increment(string $key, int $ttl): int;

    /**
     * Get stored data array, null if not exists or expired.
     */
    public function getData(string $key): ?array;

    /**
     * Store data array with TTL (seconds).
     */
    public function setData(string $key, array $data, int $ttl): bool;

    /**
     * Delete key.
     */
    public function reset(string $key): bool;

    /**
     * Get remaining TTL in seconds.
     * Returns -1 if no TTL, -2 if key not exists.
     */
    public function ttl(string $key): int;
}
