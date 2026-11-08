<?php

namespace Ryra\Core\Websocket\Server\Collector;

class FdCollector
{
    protected static array $fds = [];

    public static function set(int $id, string $controller): void
    {
        static::$fds[$id] = $controller;
    }

    public static function get(int $id, $default = null): ?string
    {
        return static::$fds[$id] ?? $default;
    }

    public static function has(int $id): bool
    {
        return isset(static::$fds[$id]);
    }

    public static function del(int $id): void
    {
        unset(static::$fds[$id]);
    }

    public static function list(): array
    {
        return static::$fds;
    }
}