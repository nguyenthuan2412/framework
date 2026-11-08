<?php

namespace Ryra\Core\RateLimit\Facade;

use Ryra\Core\Support\Facade;
use Ryra\Core\RateLimit\RateLimiter;
use Ryra\Core\RateLimit\RateLimitResult;

/**
 * @method static RateLimiter driver(string $name = 'default')
 * @method static RateLimitResult attempt(string $key, int $limit, int $windowSeconds, string $algorithm = 'sliding_window')
 * @method static bool tooManyAttempts(string $key, int $limit, int $windowSeconds, string $algorithm = 'sliding_window')
 * @method static int remaining(string $key, int $limit, int $windowSeconds, string $algorithm = 'sliding_window')
 * @method static bool reset(string $key, string $algorithm = 'sliding_window')
 *
 * @see \Ryra\Core\RateLimit\RateLimitManager
 */
class RateLimit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'rate_limit';
    }
}
