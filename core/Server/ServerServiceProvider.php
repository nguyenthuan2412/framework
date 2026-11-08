<?php

namespace Ryra\Core\Server;

use Ryra\Core\Container\ServiceProvider;
use Ryra\Core\Server\Command\StartServerCommand;

class ServerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('server', fn () => null);
    }

    public function boot(): void
    {
        $this->app->get('console')->register(StartServerCommand::class);
    }
}