<?php

namespace Ryra\Core\Datastar;

use Ryra\Core\Container\ServiceProvider;

class DatastarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: mỗi request một manager riêng để signals không lẫn giữa các coroutine
        $this->app->scoped('datastar', fn ($app) => new DatastarManager($app, (array) config('datastar', [])));
        $this->app->alias('datastar', DatastarManager::class);
    }
}
