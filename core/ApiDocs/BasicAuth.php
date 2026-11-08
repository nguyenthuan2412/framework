<?php

namespace Ryra\Core\ApiDocs;

use Ryra\Core\Contracts\Config\Repository;
use Ryra\Core\Http\Request;
use Ryra\Core\Http\Response;

class BasicAuth
{
    public function __construct(
        protected Repository $config
    ) {}

    public function handle(Request $request, \Closure $next): Response
    {
        $authEnable = $this->config->get('api_docs.basic_auth.enable');
        if ($authEnable) {
            $configUserName = $this->config->get('api_docs.basic_auth.account.username');
            $configPassword = $this->config->get('api_docs.basic_auth.account.password');

            $authHeader = $request->header('authorization', '');
            if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
                return $this->unauthorized();
            }

            $decoded = base64_decode(substr($authHeader, 6));
            [$username, $password] = explode(':', $decoded, 2);
            if ($username !== $configUserName || $password !== $configPassword) {
                return $this->unauthorized();
            }
        }

        return $next($request);
    }

    protected function unauthorized(): Response
    {
        $response = Response::text('Unauthorized')
            ->setStatusCode(401);
        $response
            ->headers
            ->set('WWW-Authenticate', 'Basic realm="My App"');

        return $response;
    }
}