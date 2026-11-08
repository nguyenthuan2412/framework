<?php

namespace Ryra\Core\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Ryra\Core\Container\ServiceProvider;

class ConsoleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('console.input', Input::class);
        $this->app->alias('console.input', InputInterface::class);

        $this->app->singleton('console.output', Output::class);
        $this->app->alias('console.output', OutputInterface::class);

        $this->app->singleton(
            'console',
            static fn($app) => new Application(
                $app,
                $app->get('console.input'),
                $app->get('console.output'),
            )
        );
    }

    public function boot(): void
    {
        $this->app->get('module.manager')->addExtend('commands', function (mixed $commands) {
            $console = $this->app->get('console');
            foreach ($commands as $command) {
                $console->register($command);
            }
        });
    }
}