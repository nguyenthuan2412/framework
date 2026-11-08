<?php

namespace Ryra\Core\Watcher\Drivers;

class FSEvents implements DriverInterface
{
    

    public function on(array $events, callable $callback): static
    {
        return $this;
    }
}