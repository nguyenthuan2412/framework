<?php

namespace Ryra\Core\Module;

use Ryra\Core\Container\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('module.manager', fn () => new ModuleManager());
        $this->app->alias('module.manager', ModuleManager::class);

        $this->app->singleton('module.installer', fn() => new ModuleInstaller(
            $this->app->get('module.manager'),
            $this->app->get('service_provider_manager')
        ));
        $this->app->alias('module.installer', ModuleInstaller::class);
    }

    public function boot(): void
    {
        global $argv;
        if (!empty($argv[1]) && $argv[1] == 'start:serve') {
            return;
        }

        $this->app->get('module.installer')->install();
    }
}