<?php

namespace Ryra\Core\RateLimit;

/**
 * Immutable result object returned from rate limiter.
 */
readonly class RateLimitResult
{
    public function __construct(
        /** Whether the request is allowed */
        public bool $allowed,

        /** Maximum requests in the window */
        public int $limit,

        /** Remaining requests in current window */
        public int $remaining,

        /** Unix timestamp when the window resets */
        public int $resetTime,

        /** Seconds until retry allowed (0 if allowed) */
        public int $retryAfter,
    ) {}

    /**
     * Standard RFC 6585 rate limit headers.
     */
    public function headers(): array
    {
        return [
            'X-RateLimit-Limit'     => (string) $this->limit,
            'X-RateLimit-Remaining' => (string) $this->remaining,
            'X-RateLimit-Reset'     => (string) $this->resetTime,
            'Retry-After'           => (string) $this->retryAfter,
        ];
    }
}
