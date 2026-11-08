<?php

namespace Ryra\Core\Redis;

use Ryra\Core\Container\ServiceProvider;

class RedisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('redis', static fn($app) => new RedisManager($app->get('config'), $app->get('pool')));

        $this->app->alias('redis', RedisManager::class);
    }
}
