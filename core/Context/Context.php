<?php

namespace Ryra\Core\Context;

use Ryra\Core\Collection\Arr;
use Ryra\Core\Coroutine\Coroutine;

class Context
{
    protected static $pool = [];

    public static function all(): ?array
    {
        $cid = Coroutine::id();
        if ($cid < 0) {
            return null;
        }

        return static::$pool[$cid] ?? null;
    }

    public static function get(string|int $key, mixed $default = null): mixed
    {
        if (static::has($key)) {
            $cid = Coroutine::id();
            return Arr::get(static::$pool[$cid], $key, $default);
        }

        return $default;
    }

    public static function has(string|int $key): bool
    {
        $cid = Coroutine::id();
        if ($cid < 0) {
            return false;
        }

        return Arr::has(static::$pool[$cid] ?? [], $key);
    }

    public static function put(string $key, mixed $item): void
    {
        $cid = Coroutine::id();
        if ($cid > 0) {
            if (empty(static::$pool[$cid])) {
                static::$pool[$cid] = [];
            }
            Arr::set(static::$pool[$cid], $key, $item);
        }
    }

    public static function push(string $key, mixed $item): void
    {
        $cid = Coroutine::id();
        if ($cid > 0) {
            if (!isset(static::$pool[$cid][$key])) {
                static::$pool[$cid][$key] = [];
            }
            static::$pool[$cid][$key][] = $item;
        }
    }

    public static function delete($key = null): void
    {
        $cid = Coroutine::id();

        if ($cid > 0) {
            if (is_null($key)) {
                unset(static::$pool[$cid]);
                return;
            }

            Arr::forget(static::$pool[$cid], [$key]);
        }
    }

    public static function copy(int $fromCid, int $toCid): void
    {
        if (isset(static::$pool[$fromCid])) {
            static::$pool[$toCid] = static::$pool[$fromCid];
        }
    }
}
