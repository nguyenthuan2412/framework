<?php

namespace Ryra\Core\Pool;

use Ryra\Core\Container\ServiceProvider;

class PoolServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('pool', PoolManager::class);
        $this->app->alias('pool', \Ryra\Core\Contracts\Pool\PoolManager::class);
    }
}