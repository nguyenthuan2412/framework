<?php

namespace Ryra\Core\Middleware;

use Ryra\Core\Http\Exception\TokenMismatchException;
use Ryra\Core\Http\Request;
use Ryra\Core\Http\Response;

class VerifyCsrfToken
{
    public function handle(Request $request, \Closure $next): Response
    {
        $token = $request->getSession()->get('_token');
        if (
            in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])
            && (
                empty($token)
                || (!empty($token) && $token !== $request->input('_token'))
            )
        ) {
            throw new TokenMismatchException();
        }

        return $next($request);
    }
}