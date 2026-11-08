<?php

namespace Ryra\Core\RateLimit\Algorithms;

use Ryra\Core\RateLimit\Contracts\AlgorithmInterface;
use Ryra\Core\RateLimit\Contracts\RateLimiterDriverInterface;
use Ryra\Core\RateLimit\RateLimitResult;

/**
 * Token Bucket algorithm.
 *
 * Bucket có capacity token. Token được nạp liên tục theo refillRate.
 * Mỗi request tiêu thụ 1 token. Cho phép burst đến capacity.
 *
 * Ưu: Phổ biến nhất, kiểm soát được burst tự nhiên (không cắt cứng).
 * Nhược: Logic phức tạp hơn, cần lưu state {tokens, last_refill}.
 *
 * Dùng cho: API gateway, bất kỳ nơi cần cho phép burst ngắn nhưng giới hạn rate tổng thể.
 *
 * @param float $refillRate tokens nạp mỗi giây (VD: 1.0 = 1 token/s, 0.5 = 1 token/2s)
 */
class TokenBucket implements AlgorithmInterface
{
    public function __construct(private float $refillRate = 1.0) {}

    public function check(
        RateLimiterDriverInterface $driver,
        string $key,
        int $limit,       // bucket capacity
        int $windowSeconds, // dùng làm TTL storage
    ): RateLimitResult {
        $now        = microtime(true);
        $storageKey = $key . ':tb';

        $data = $driver->getData($storageKey);

        if ($data === null) {
            // First request: full bucket minus 1
            $tokens     = (float) ($limit - 1);
            $lastRefill = $now;
        } else {
            $elapsed  = $now - (float) ($data['last_refill'] ?? $now);
            $refilled = $elapsed * $this->refillRate;
            $tokens   = min((float) $limit, (float) ($data['tokens'] ?? $limit) + $refilled);

            if ($tokens < 1.0) {
                // Not enough tokens — denied
                $tokensNeeded = 1.0 - $tokens;
                $retryAfter   = (int) ceil($tokensNeeded / $this->refillRate);

                $driver->setData($storageKey, [
                    'tokens'      => $tokens,
                    'last_refill' => $now,
                ], $windowSeconds);

                return new RateLimitResult(
                    allowed: false,
                    limit: $limit,
                    remaining: 0,
                    resetTime: (int) ($now + $retryAfter),
                    retryAfter: $retryAfter,
                );
            }

            $tokens     -= 1.0;
            $lastRefill  = $now;
        }

        $driver->setData($storageKey, [
            'tokens'      => $tokens,
            'last_refill' => $lastRefill,
        ], $windowSeconds);

        $remaining     = (int) floor($tokens);
        $timeToFull    = ($limit - $tokens) / $this->refillRate;

        return new RateLimitResult(
            allowed: true,
            limit: $limit,
            remaining: $remaining,
            resetTime: (int) ($now + $timeToFull),
            retryAfter: 0,
        );
    }

    public function reset(RateLimiterDriverInterface $driver, string $key): bool
    {
        return $driver->reset($key . ':tb');
    }
}
