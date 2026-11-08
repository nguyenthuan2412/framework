<?php

namespace Ryra\Core\Filesystem;

use Ryra\Core\Container\ServiceProvider;

class FilesystemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('filesystem', fn ($app) => new FilesystemManager($app->get('config')));
        $this->app->alias('filesystem', FilesystemInterface::class);
    }
}