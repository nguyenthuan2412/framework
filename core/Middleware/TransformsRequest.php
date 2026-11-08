<?php

namespace Ryra\Core\Middleware;

use Ryra\Core\Http\Request;

class TransformsRequest
{
    public function handle(Request $request, \Closure $next): mixed
    {
        foreach ($request->query as $key => $value) {
            $request->query->set($key, $this->transform($key, $value));
        }

        foreach ($request->request as $key => $value) {
            $request->request->set($key, $this->transform($key, $value));
        }

        return $next($request);
    }

    protected function transform(string $key, mixed $value): mixed
    {
        return $value;
    }
}