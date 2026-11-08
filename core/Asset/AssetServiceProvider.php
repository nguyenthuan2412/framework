<?php

namespace Ryra\Core\Asset;

use Ryra\Core\Container\ServiceProvider;

class AssetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->app->get('route.collection')->register(AssetController::class);
    }
}