<?php

namespace Ryra\Core\Process;

use Ryra\Core\Container\ServiceProvider;

class ProcessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->transient('process', fn() => new Process());
    }
}