<?php

namespace Ryra\Core\Pool\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static void register(string $name, \Ryra\Core\Contracts\Pool\ConnectionFactory $connectionFactory, \Ryra\Core\Contracts\Pool\PoolOption|array $options)
 * @method static bool has(string $name)
 * @method static \Ryra\Core\Pool\Pool|null get(string $name)
 * @method static void release(string $name, object $connection)
 */
class Pool extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'pool';
    }
}