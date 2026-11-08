<?php

namespace Ryra\Core\Container;

use Ryra\Core\App;

class ServiceProvider
{
    public function __construct(protected App $app) {}

    public function register(): void
    {
        // Handle regiser service
    }

    public function boot(): void
    {
        // Handle boot service
    }
}