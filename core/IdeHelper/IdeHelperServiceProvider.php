<?php

namespace Ryra\Core\IdeHelper;

use Ryra\Core\Container\ServiceProvider;

class IdeHelperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('ide_helper', function () {
            
        });
    }
}