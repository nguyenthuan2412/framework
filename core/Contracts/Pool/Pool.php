<?php

namespace Ryra\Core\Contracts\Pool;

interface Pool
{
    public function get(): object;

    public function release(object $connection): void;

    public function getConnectionsInChannel(): int;

    public function getCurrentConnections(): int;

    public function flush(): void;
}