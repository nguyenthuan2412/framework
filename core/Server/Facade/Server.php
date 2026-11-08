<?php

namespace Ryra\Core\Server\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static int|false task(string $task, mixed $data, int $taskWorkerIndex = -1)
 */
class Server extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'server';
    }
}