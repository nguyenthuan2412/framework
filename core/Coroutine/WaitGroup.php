<?php

namespace Ryra\Core\Coroutine;

class WaitGroup extends \Swoole\Coroutine\WaitGroup
{
    public static function create()
    {
        return new static();
    }
}