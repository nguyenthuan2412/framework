<?php

namespace Ryra\Core\Pool;

use Ryra\Core\Contracts\Pool\ConnectionFactory;
use Ryra\Core\Contracts\Pool\PoolOption as PoolOptionContract;
use Ryra\Core\Contracts\Pool\Pool as PoolContract;
use Ryra\Core\Pool\PoolOption;
use Ryra\Core\Pool\Pool;

class PoolManager implements \Ryra\Core\Contracts\Pool\PoolManager
{
    /**
     * @var PoolContract[]
     */
    protected array $pools = [];

    public function register(string $name, ConnectionFactory $connectionFactory, PoolOptionContract|array $options): void
    {
        $options = $options instanceof PoolOptionContract ? $options : PoolOption::fromArray($options);
        $this->pools[$name] = new Pool($connectionFactory, $options);
    }

    public function has(string $name): bool
    {
        return isset($this->pools[$name]);
    }

    public function get(string $name): ?PoolContract
    {
        return $this->pools[$name] ?? null;
    }

    public function release(string $name, object $connection): void
    {
        $this->pools[$name]->release($connection);
    }
}