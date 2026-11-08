<?php

namespace Ryra\Core\Cache\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool set(string $key, mixed $value, null|int|\DateInterval $ttl = null)
 * @method static bool delete(string $key)
 * @method static bool clear()
 * @method static iterable getMultiple(iterable $keys, mixed $default = null)
 * @method static bool setMultiple(iterable $values, null|int|\DateInterval $ttl = null)
 * @method static bool deleteMultiple(iterable $keys)
 * @method static bool has(string $key)
 * @method static mixed remember(string $key, int|\DateInterval $ttl, callable $callback)
 * @method static \Psr\SimpleCache\CacheInterface driver(?string $driver = null)
 *
 * @see \Ryra\Core\Cache\CacheManager
 */
class Cache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'cache';
    }
}
