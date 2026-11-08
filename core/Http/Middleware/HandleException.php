<?php

namespace Ryra\Core\Http\Middleware;

use Throwable;
use Ryra\Core\App;
use Ryra\Core\Http\Request;
use Ryra\Core\Http\Response;

class HandleException
{
    public function __construct(protected App $app) {}

    public function handle(Request $request, \Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (\Throwable $th) {
            $response = $this->app->get('http.handle_exception')
                ->render($request, $th);
        }

        return $response;
    }
}