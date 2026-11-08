<?php

namespace Ryra\Core\Contracts\Pool;

interface PoolOption
{
    public static function fromArray(array $array): static;

    public function getMaxConnections(): int;

    public function setMaxConnections(int $maxConnections): static;

    public function getMinConnections(): int;

    public function setMinConnections(int $minConnections): static;

    public function getHeartbeat(): float;

    public function setHeartbeat(float $heartbeat): static;

    public function getWaitTimeout(): float;

    public function setWaitTimeout(float $waitTimeout): static;

    public function getMaxIdleTime(): float;

    public function setMaxIdleTime(float $maxIdleTime): static;

    public function getEvents(): array;

    public function setEvents(array $events): static;
}