<?php

namespace Ryra\Core\Hashing;

use Ryra\Core\Container\ServiceProvider;

class HashServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('hash', fn() => new HashManager($this->app->get('config')));
        $this->app->singleton('hash.driver', fn() => $this->app->get('hash')->driver());
    }
}