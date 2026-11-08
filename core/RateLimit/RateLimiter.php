<?php

namespace Ryra\Core\RateLimit;

use Ryra\Core\RateLimit\Algorithms\FixedWindow;
use Ryra\Core\RateLimit\Algorithms\SlidingWindow;
use Ryra\Core\RateLimit\Algorithms\TokenBucket;
use Ryra\Core\RateLimit\Contracts\AlgorithmInterface;
use Ryra\Core\RateLimit\Contracts\RateLimiterDriverInterface;

/**
 * Main rate limiter class.
 *
 * Usage:
 *   $result = $rateLimiter->attempt('user:123', limit: 60, windowSeconds: 60);
 *   if (!$result->allowed) { return 429; }
 */
class RateLimiter
{
    private array $algorithms;

    public function __construct(private RateLimiterDriverInterface $driver)
    {
        $this->algorithms = [
            'fixed_window'   => new FixedWindow(),
            'sliding_window' => new SlidingWindow(),
            'token_bucket'   => new TokenBucket(),
        ];
    }

    /**
     * Attempt a request. Records the attempt and returns result.
     *
     * @param string $key           Unique key (user ID, IP, route, etc.)
     * @param int    $limit         Max allowed requests
     * @param int    $windowSeconds Time window in seconds
     * @param string $algorithm     fixed_window | sliding_window | token_bucket
     */
    public function attempt(
        string $key,
        int $limit,
        int $windowSeconds,
        string $algorithm = 'sliding_window',
    ): RateLimitResult {
        return $this->resolveAlgorithm($algorithm)->check($this->driver, $key, $limit, $windowSeconds);
    }

    /**
     * Check if too many attempts (does NOT record the attempt).
     */
    public function tooManyAttempts(
        string $key,
        int $limit,
        int $windowSeconds,
        string $algorithm = 'sliding_window',
    ): bool {
        return !$this->attempt($key, $limit, $windowSeconds, $algorithm)->allowed;
    }

    /**
     * Get remaining attempts without recording.
     * Note: for SlidingWindow/TokenBucket this reads current state.
     */
    public function remaining(
        string $key,
        int $limit,
        int $windowSeconds,
        string $algorithm = 'sliding_window',
    ): int {
        return $this->attempt($key, $limit, $windowSeconds, $algorithm)->remaining;
    }

    /**
     * Reset the counter for a key.
     */
    public function reset(string $key, string $algorithm = 'sliding_window'): bool
    {
        return $this->resolveAlgorithm($algorithm)->reset($this->driver, $key);
    }

    /**
     * Return a new instance using a different driver.
     */
    public function withDriver(RateLimiterDriverInterface $driver): static
    {
        $clone = clone $this;
        $clone->driver = $driver;
        return $clone;
    }

    /**
     * Register a custom algorithm.
     *
     * $rateLimiter->registerAlgorithm('leaky_bucket', new LeakyBucket());
     */
    public function registerAlgorithm(string $name, AlgorithmInterface $algorithm): void
    {
        $this->algorithms[$name] = $algorithm;
    }

    /**
     * Build a TokenBucket instance with custom refill rate.
     *
     * $rateLimiter->tokenBucket(refillRate: 2.0)->attempt(...);
     */
    public function tokenBucket(float $refillRate = 1.0): static
    {
        $clone = clone $this;
        $clone->algorithms['token_bucket'] = new TokenBucket($refillRate);
        return $clone;
    }

    private function resolveAlgorithm(string $name): AlgorithmInterface
    {
        if (!isset($this->algorithms[$name])) {
            throw new \InvalidArgumentException("Unknown rate limit algorithm: [{$name}]. Available: " . implode(', ', array_keys($this->algorithms)));
        }

        return $this->algorithms[$name];
    }
}
