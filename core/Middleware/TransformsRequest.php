<?php

namespace Ryra\Core\Middleware;

use Ryra\Core\Http\Request;

class TransformsRequest
{
    public function handle(Request $request, \Closure $next): mixed
    {
        // Kiểm tra trước để request không có input thì không phải tạo bag (bag được tạo lazy).
        if ($request->hasQueryInput()) {
            $query = $request->query;
            foreach ($query as $key => $value) {
                $query->set($key, $this->transform($key, $value));
            }
        }

        if ($request->hasBodyInput()) {
            $body = $request->request;
            foreach ($body as $key => $value) {
                $body->set($key, $this->transform($key, $value));
            }
        }

        return $next($request);
    }

    protected function transform(string $key, mixed $value): mixed
    {
        return $value;
    }
}