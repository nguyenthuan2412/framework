<?php

namespace Ryra\Core\Config;

use Ryra\Core\Container\ServiceProvider;

class ConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('config', Repository::class);
        $this->app->alias('config', \Ryra\Core\Contracts\Config\Repository::class);
    }

    public function boot(): void
    {
        $fileConfigs = \glob($this->app->getConfigPath() . '/*.php');
        foreach ($fileConfigs as $file) {
            $name = \pathinfo($file, PATHINFO_FILENAME);
            $config = require_once $file;
            if (is_array($config)) {
                $this->app->get('config')->set($name, $config);
            }
        }
    }
}