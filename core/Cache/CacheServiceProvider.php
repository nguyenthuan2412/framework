<?php

namespace Ryra\Core\Cache;

use Ryra\Core\Cache\CacheManager;
use Ryra\Core\Container\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('cache', fn () => new CacheManager($this->app));
    }
}