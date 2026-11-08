<?php

namespace Ryra\Core\Route;

use Ryra\Core\App;
use Ryra\Core\Url\Url;

class RouteUrlGenerator
{
    public function __construct(
        protected readonly RouteCollectionInterfade $routes,
    ) {}

    public function route(string $name, array $params = [], bool $absolute = true, string|int|null $version = null): ?Url
    {
        $raw = $this->routes->getUrl($name, $params, $absolute, $version);

        if ($raw === null) {
            return null;
        }

        return Url::parse($raw);
    }

    public function to(string $path, array $query = [], bool $secure = false): Url
    {
        return Url::to($path, $query, $secure);
    }

    public function secure(string $path, array $query = []): Url
    {
        return $this->to($path, $query, secure: true);
    }

    public function asset(string $path, bool $secure = false): Url
    {
        $assetUrl = \config('app.asset_url', App::baseUrl());
        $url = Url::parse($assetUrl)->appendPath(\ltrim($path, '/'));

        if ($secure) {
            $url = $url->withScheme('https');
        }

        return $url;
    }

    public function signedRoute(
        string $name,
        array $params = [],
        \DateTimeInterface|int|null $expiration = null,
        ?string $key = null,
        string|int|null $version = null,
    ): ?Url {
        $url = $this->route($name, $params, version: $version);

        if ($url === null) {
            return null;
        }

        if ($expiration !== null) {
            return $url->temporarySignedUrl($expiration, $key);
        }

        return $url->signed($key);
    }
}
