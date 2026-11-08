<?php

namespace Ryra\Core\Generator;

use Ryra\Core\Container\ServiceProvider;
use Ryra\Core\Generator\Command\GenControllerCommand;
use Ryra\Core\Generator\Command\GenDTOCommand;
use Ryra\Core\Generator\Command\GenMailCommand;
use Ryra\Core\Generator\Command\GenMiddlewareCommand;
use Ryra\Core\Generator\Command\GenModelCommand;
use Ryra\Core\Generator\Command\GenModuleCommand;
use Ryra\Core\Generator\Command\GenProviderCommand;

class GeneratorServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $console = $this->app->get('console');

        $console->register(GenControllerCommand::class);
        $console->register(GenModelCommand::class);
        $console->register(GenDTOCommand::class);
        $console->register(GenProviderCommand::class);
        $console->register(GenMailCommand::class);
        $console->register(GenMiddlewareCommand::class);
        $console->register(GenModuleCommand::class);
    }
}
