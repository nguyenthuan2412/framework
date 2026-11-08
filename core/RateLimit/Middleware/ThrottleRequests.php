<?php

namespace Ryra\Core\RateLimit\Middleware;

use Closure;
use Ryra\Core\Context\Context;
use Ryra\Core\Http\Request;
use Ryra\Core\Http\Response;
use Ryra\Core\RateLimit\RateLimitManager;
use Ryra\Core\RateLimit\RateLimitResult;

/**
 * Throttle middleware — plug into any route or group.
 *
 * Usage via Route Attribute:
 *   #[Group('api/orders', middlewares: [AuthMiddleware::class, [ThrottleRequests::class, 60, 60]])]
 *
 *   // Token bucket: 100 capacity, refill 2 tokens/s
 *   #[Get('/', middlewares: [[ThrottleRequests::class, 100, 60, 'redis', 'token_bucket']])]
 *
 * Parameters (positional):
 *   $maxAttempts  - Max requests allowed in window (default: 60)
 *   $decaySeconds - Window size in seconds (default: 60)
 *   $driver       - redis | database | file | memory | default (default: 'default')
 *   $algorithm    - fixed_window | sliding_window | token_bucket (default: 'sliding_window')
 *   $by           - Key strategy: ip | user | custom_string (default: 'ip')
 */
class ThrottleRequests
{
    public function __construct(private RateLimitManager $manager) {}

    public function handle(
        Request $request,
        Closure $next,
        int $maxAttempts = 60,
        int $decaySeconds = 60,
        string $driver = 'default',
        string $algorithm = 'sliding_window',
        string $by = 'ip',
    ): mixed {
        $key    = $this->resolveKey($request, $by);
        $result = $this->manager->driver($driver)->attempt($key, $maxAttempts, $decaySeconds, $algorithm);

        if (!$result->allowed) {
            return Response::json([
                'result'  => 'error',
                'message' => 'Too many requests. Please slow down.',
            ], 429, $result->headers());
        }

        $response = $next($request);

        // Append rate limit headers to successful responses
        foreach ($result->headers() as $header => $value) {
            $response->headers->set($header, $value);
        }

        return $response;
    }

    private function resolveKey(Request $request, string $by): string
    {
        return match ($by) {
            'user' => 'user:' . (Context::get('user.user_id') ?? $request->ip()),
            'ip'   => 'ip:' . $request->ip(),
            default => $by . ':' . $request->ip(),
        };
    }
}
