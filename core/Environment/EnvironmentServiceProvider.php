<?php

namespace Ryra\Core\Environment;

use Ryra\Core\App;
use Ryra\Core\Container\ServiceProvider;

class EnvironmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('env', fn () => Environment::createMutable(
            $this->app->getBasePath(),
            App::environmentFile(),
        ));
    }

    public function boot(): void
    {
        $this->app->get('env')->load();
    }
}