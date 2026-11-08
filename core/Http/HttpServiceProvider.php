<?php

declare(strict_types=1);

namespace Ryra\Core\Http;

use Ryra\Core\Container\ServiceProvider;
use Ryra\Core\Http\Exception\Handler;

class HttpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped('request', Request::class);
        $this->app->alias('request', Request::class);
        $this->app->singleton('http.handle_exception', fn() => new Handler($this->app));
    }

    public function boot(): void
    {
        // Set trusted proxies một lần khi bootstrap (phù hợp với Swoole)
        Request::setTrustedProxies(
            config('app.trusted_proxies', []),
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $this->app->get('twig.loader')->addPath(join_paths(__DIR__, 'view'), 'http_exception');
    }
}