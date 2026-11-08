<?php

namespace Ryra\Modules\Example\Providers;

use Ryra\Core\Container\ServiceProvider;
use Ryra\Modules\Example\Services\GreetingService;

class ExampleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GreetingService::class, fn () => new GreetingService());
    }

    public function boot(): void
    {
        //
    }
}
