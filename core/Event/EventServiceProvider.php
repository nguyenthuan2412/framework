<?php

namespace Ryra\Core\Event;

use Ryra\Core\Container\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('events', EventDispatcher::class);
        $this->app->alias('events', \Ryra\Core\Contracts\Event\EventDispatcher::class);
    }
}