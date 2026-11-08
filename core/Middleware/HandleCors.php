<?php

namespace Ryra\Core\Middleware;

use Ryra\Core\Http\Request;
use Ryra\Core\Http\Response;

class HandleCors
{
    public function handle(Request $request, \Closure $next): Response
    {
        if ($request->isMethod('options')) {
            $response = new Response('', 200);
        } else {
            $response = $next($request);
        }

        if ($request->headers->has('origin')) {
            $allowOrigin = $this->allowOrigin($request);
            $allowPath = $this->allowPath($request);
            if (!$allowOrigin || !$allowPath) {
                return $response;
            }

            $response->withHeader('Access-Control-Allow-Origin', $request->headers->get('origin'));
            $this->configureAllowCredentials($response);
            $this->configureAllowedMethods($response);
            $this->configureAllowedHeaders($response);
            $this->configureExposedHeaders($response);
            $this->configureMaxAge($response);
        }

        return $response;
    }

    protected function allowOrigin(Request $request): bool
    {
        $headers = config('cors.allowed_origins', []);
        $origin = $request->headers->get('origin');
        return in_array($origin, $headers);
    }

    protected function allowPath(Request $request): bool
    {
        $allowPath = false;
        $paths = config('cors.paths', []);
        foreach ($paths as $path) {
            if ($request->is($path)) {
                $allowPath = true;
                break;
            }
        }
        return $allowPath;
    }

    private function configureAllowedMethods(Response $response): void
    {
        $allowMethods = config('cors.allowed_methods', null);
        if (is_null($allowMethods)) {
            return;
        }
        $allowMethods = implode(', ', array_map('strtoupper', $allowMethods));
        $response->headers->set('Access-Control-Allow-Methods', $allowMethods);
    }

    private function configureAllowedHeaders(Response $response): void
    {
        $allowMethods = config('cors.allowed_headers', ['*']);
        $allowHeaders = implode(', ', $allowMethods);
        $response->headers->set('Access-Control-Allow-Headers', $allowHeaders);
    }

    private function configureAllowCredentials(Response $response): void
    {
        $supportsCredentials = config('cors.supports_credentials', false);
        if ($supportsCredentials) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
    }

    private function configureExposedHeaders(Response $response): void
    {
        $exposedHeaders = config('exposed_headers', null);
        if ($exposedHeaders) {
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $exposedHeaders));
        }
    }

    private function configureMaxAge(Response $response): void
    {
        $maxAge = config('cors.max_age', null);
        if (!is_null($maxAge)) {
            $response->headers->set('Access-Control-Max-Age', (string) $maxAge);
        }
    }
}