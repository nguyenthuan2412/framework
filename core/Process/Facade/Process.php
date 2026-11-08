<?php

namespace Ryra\Core\Process\Facade;

use Ryra\Core\Support\Facade;

class Process extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'process';
    }
}