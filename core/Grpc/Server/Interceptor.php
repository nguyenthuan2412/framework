<?php

namespace Ryra\Core\Grpc\Server;

use Closure;

/**
 * Interceptor (middleware) cho gRPC server.
 *
 * $request là message (unary / server streaming) hoặc iterable các message (client streaming).
 * Gọi $next($request, $context) để chạy tiếp, giá trị trả về là response (hoặc iterable khi server streaming).
 */
interface Interceptor
{
    public function intercept(mixed $request, ServerContext $context, Closure $next): mixed;
}
