<?php

namespace Ryra\Core\ErrorHandler;

use Ryra\Core\Container\ServiceProvider;

class ErrorHandlerProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('error_handler', fn () => new Handler());
    }

    public function boot(): void
    {
        $this->app->get('error_handler')->registerErrorHandler();
        $this->app->get('error_handler')->registerExceptionHandler();
    }
}