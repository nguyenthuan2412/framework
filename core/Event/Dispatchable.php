<?php

namespace Ryra\Core\Event;

use Ryra\Core\App;

trait Dispatchable
{
    public static function dispatch(mixed ...$params): void
    {
        App::service('events')->dispatch(new static(...$params));
    }

    public static function dispatchIf(bool $condition, mixed ...$params): void
    {
        if ($condition) {
            static::dispatch(...$params);
        }
    }

    public static function dispatchUnless(bool $condition, mixed ...$params): void
    {
        if (!$condition) {
            static::dispatch(...$params);
        }
    }
}