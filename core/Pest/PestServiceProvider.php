<?php

namespace Ryra\Core\Pest;

use Ryra\Core\Container\ServiceProvider;
use Ryra\Core\Pest\Commands\TestCommand;

class PestServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->get('console')->register(TestCommand::class);
    }
}