<?php

namespace Ryra\Core\Date;

use Ryra\Core\Container\ServiceProvider;

class DateServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        \date_default_timezone_set(\config('app.timezone'));
    }
}