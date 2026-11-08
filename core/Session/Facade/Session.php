<?php

namespace Ryra\Core\Session\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool has(string $key)
 * @method static bool hasAny(string $key)
 * @method static void put(string $key, mixed $value = null)
 * @method static mixed pull(string $key, mixed $value = null)
 * @method static void push(string $key, mixed $value)
 * @method static mixed increment(string $key, $amount = 1)
 * @method static mixed decrement(string $key, $amount = 1)
 * @method static void flush()
 * @method static string regenerateToken()
 * @method static SessionHandlerInterface getHandler()
 * @method static bool isStarted()
 * @method static string getName()
 * @method static void setName(string $name)
 * @method static string id()
 * @method static string getId()
 * @method static void setId(string $id)
 * @method static bool isValidId(string $id)
 * @method static string getPreviousUrl(int $number = 1)
 * @method static void flash(string $key, mixed $value)
 */
class Session extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'session';
    }
}