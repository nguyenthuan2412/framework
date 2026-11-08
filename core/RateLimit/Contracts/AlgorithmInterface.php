<?php

namespace Ryra\Core\RateLimit\Contracts;

use Ryra\Core\RateLimit\RateLimitResult;

interface AlgorithmInterface
{
    /**
     * Check if request is allowed and record it.
     *
     * @param string $key          Unique identifier (user, IP, route...)
     * @param int    $limit        Max requests allowed
     * @param int    $windowSeconds Time window in seconds
     */
    public function check(
        RateLimiterDriverInterface $driver,
        string $key,
        int $limit,
        int $windowSeconds,
    ): RateLimitResult;

    /**
     * Reset counter for given key.
     */
    public function reset(RateLimiterDriverInterface $driver, string $key): bool;
}
