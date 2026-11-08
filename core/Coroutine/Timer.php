<?php

namespace Ryra\Core\Coroutine;

class Timer
{
    public static function interval(callable $callback, int $ms, array $params = []): bool|int
    {
        return \Swoole\Timer::tick($ms, $callback, ...$params);
    }

    public static function timeout(callable $callback, int $ms, array $params = []): bool|int
    {
        return \Swoole\Timer::after($ms, $callback, ...$params);
    }

    public static function clear(int $timerId): bool
    {
        return \Swoole\Timer::clear($timerId);
    }

    public static function exists(int $timerId): bool
    {
        return \Swoole\Timer::exists($timerId);
    }

    public static function clearAll(): bool
    {
        return \Swoole\Timer::clearAll();
    }
}