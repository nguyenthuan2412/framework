<?php

namespace Ryra\Core\Jwt;

use Ryra\Core\Container\ServiceProvider;
use Ryra\Core\Jwt\Blacklist\BlacklistManager;

class JWTServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('jwt.blacklist', fn ($app) => new BlacklistManager($app));
        $this->app->singleton('jwt', fn ($app) => new JWTManager($app->get('jwt.blacklist')));
    }
}
