<?php

namespace Ryra\Core\RateLimit\Algorithms;

use Ryra\Core\RateLimit\Contracts\AlgorithmInterface;
use Ryra\Core\RateLimit\Contracts\RateLimiterDriverInterface;
use Ryra\Core\RateLimit\RateLimitResult;

/**
 * Fixed Window algorithm.
 *
 * Chia thời gian thành các window cố định (VD: 0-59s, 60-119s...).
 * Counter reset về 0 khi sang window mới.
 *
 * Ưu: Đơn giản, dùng ít memory nhất.
 * Nhược: Bị double-burst tại ranh giới window.
 *
 * Dùng cho: Quota ngày/tháng, billing limit, internal service throttle.
 */
class FixedWindow implements AlgorithmInterface
{
    public function check(
        RateLimiterDriverInterface $driver,
        string $key,
        int $limit,
        int $windowSeconds,
    ): RateLimitResult {
        $now         = time();
        $windowIndex = (int) floor($now / $windowSeconds);
        $windowKey   = $key . ':fw:' . $windowIndex;
        $windowStart = $windowIndex * $windowSeconds;
        $resetTime   = $windowStart + $windowSeconds;

        $count     = $driver->increment($windowKey, $windowSeconds);
        $allowed   = $count <= $limit;
        $remaining = max(0, $limit - $count);

        return new RateLimitResult(
            allowed: $allowed,
            limit: $limit,
            remaining: $remaining,
            resetTime: $resetTime,
            retryAfter: $allowed ? 0 : max(1, $resetTime - $now),
        );
    }

    public function reset(RateLimiterDriverInterface $driver, string $key): bool
    {
        $windowIndex = (int) floor(time() / 60);
        return $driver->reset($key . ':fw:' . $windowIndex);
    }
}
