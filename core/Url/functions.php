<?php

use Ryra\Core\App;
use Ryra\Core\Url\Url;
use Ryra\Core\Route\RouteUrlGenerator;

if (!function_exists('url')) {
    function url(?string $path = null, array $query = [], bool $secure = false): Url
    {
        if ($path === null) {
            return Url::current();
        }

        return Url::to($path, $query, $secure);
    }
}

if (!function_exists('secure_url')) {
    function secure_url(string $path, array $query = []): Url
    {
        return url($path, $query, secure: true);
    }
}

if (!function_exists('signed_url')) {
    function signed_url(
        string $name,
        array $params = [],
        \DateTimeInterface|int|null $expiration = null,
    ): ?Url {
        return App::service(RouteUrlGenerator::class)
            ->signedRoute($name, $params, $expiration);
    }
}
