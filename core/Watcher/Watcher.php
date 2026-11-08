<?php

namespace Ryra\Core\Watcher;

use Ryra\Core\Watcher\Drivers\DriverInterface;
use Ryra\Core\Watcher\Drivers\FSEvents;
use Ryra\Core\Watcher\Drivers\Inotify;
use Ryra\Core\Watcher\Drivers\Polling;

class Watcher
{
    public static function create(bool $polling = false): DriverInterface
    {
        if (!$polling) {
            $os = PHP_OS;
            if (stripos($os, 'Linux') !== false) {
                return new Inotify();
            }
            
            if (stripos($os, 'Darwin') !== false) {
                return new FSEvents();
            }
        }

        return new Polling();
    }
}