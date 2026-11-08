<?php

namespace Ryra\Core\Contracts\Pool;

use Ryra\Core\Contracts\Pool\PoolOption;
use Ryra\Core\Contracts\Pool\Pool;

interface PoolManager
{
    public function register(string $name, ConnectionFactory $connectionFactory, PoolOption|array $options): void;

    public function has(string $name): bool;

    public function get(string $name): ?Pool;

    public function release(string $name, object $connection): void;
}