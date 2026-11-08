<?php

namespace Ryra\Core\RateLimit\Algorithms;

use Ryra\Core\RateLimit\Contracts\AlgorithmInterface;
use Ryra\Core\RateLimit\Contracts\RateLimiterDriverInterface;
use Ryra\Core\RateLimit\RateLimitResult;

/**
 * Sliding Window Log algorithm.
 *
 * Lưu timestamp của từng request trong cửa sổ thời gian trượt.
 * Loại bỏ các timestamp cũ hơn (now - window) trước mỗi lần check.
 *
 * Ưu: Chính xác nhất, không bị double-burst.
 * Nhược: Tốn memory hơn Fixed Window (lưu từng timestamp).
 *
 * Dùng cho: Login throttle, public API anti-abuse, bất kỳ nơi cần chặn burst.
 */
class SlidingWindow implements AlgorithmInterface
{
    public function check(
        RateLimiterDriverInterface $driver,
        string $key,
        int $limit,
        int $windowSeconds,
    ): RateLimitResult {
        $now         = time();
        $windowStart = $now - $windowSeconds;
        $storageKey  = $key . ':sw';

        $data       = $driver->getData($storageKey) ?? ['timestamps' => []];
        $timestamps = array_values(
            array_filter($data['timestamps'] ?? [], fn($t) => $t > $windowStart)
        );

        $count   = count($timestamps);
        $allowed = $count < $limit;

        if ($allowed) {
            $timestamps[] = $now;
            $count++;
        }

        // Store with window + 1s buffer
        $driver->setData($storageKey, ['timestamps' => $timestamps], $windowSeconds + 1);

        $remaining = max(0, $limit - $count);

        // Reset time = when the oldest request in window expires
        $oldestTimestamp = !empty($timestamps) ? min($timestamps) : $now;
        $resetTime       = $oldestTimestamp + $windowSeconds;
        $retryAfter      = $allowed ? 0 : max(1, $resetTime - $now);

        return new RateLimitResult(
            allowed: $allowed,
            limit: $limit,
            remaining: $remaining,
            resetTime: $resetTime,
            retryAfter: $retryAfter,
        );
    }

    public function reset(RateLimiterDriverInterface $driver, string $key): bool
    {
        return $driver->reset($key . ':sw');
    }
}
