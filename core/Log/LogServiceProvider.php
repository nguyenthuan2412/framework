<?php

namespace Ryra\Core\Log;

use Ryra\Core\Container\ServiceProvider;

class LogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('log', fn ($app) => new LoggerFactory($app['config']));
    }
}