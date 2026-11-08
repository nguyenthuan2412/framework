<?php

namespace Ryra\Core\Pool;

class PoolOption implements \Ryra\Core\Contracts\Pool\PoolOption

{
    /**
     * Min connections of pool.
     * This means the pool will create $minConnections connections when
     * pool initialization.
     */
    private int $minConnections;

    /**
     * Max connections of pool.
     */
    private int $maxConnections;

    /**
     * The timeout of pop a connection.
     * Default value is 3 seconds.
     */
    private float $waitTimeout;

    /**
     * Heartbeat of connection.
     * If the value is 10, then means 10 seconds.
     * If the value is -1, then means does not need the heartbeat.
     * Default value is -1.
     */
    private float $heartbeat;

    /**
     * The max idle time for connection.
     */
    private float $maxIdleTime;

    /**
     * The events which will be triggered by releasing connection and so on.
     * @var array<int, string>
     */
    private array $events;

    public function __construct(
        int $minConnections = 1,
        int $maxConnections = 10,
        float $waitTimeout = 3.0,
        float $heartbeat = -1,
        float $maxIdleTime = 60.0,
        array $events = [],
    ) {
        $this->minConnections = $minConnections;
        $this->maxConnections = $maxConnections;
        $this->waitTimeout = $waitTimeout;
        $this->heartbeat = $heartbeat;
        $this->maxIdleTime = $maxIdleTime;
        $this->events = $events;
    }

    public static function fromArray(array $array): static
    {
        return new static(
            $array['min_connections'] ?? 1,
            $array['max_connections'] ?? 10,
            $array['wait_timeout'] ?? 3.0,
            $array['heartbeat'] ?? -1,
            $array['max_idle_time'] ?? 60.0,
            $array['events'] ?? [],
        );
    }

    public function getMaxConnections(): int
    {
        return $this->maxConnections;
    }

    public function setMaxConnections(int $maxConnections): static
    {
        $this->maxConnections = $maxConnections;
        return $this;
    }

    public function getMinConnections(): int
    {
        return $this->minConnections;
    }

    public function setMinConnections(int $minConnections): static
    {
        $this->minConnections = $minConnections;
        return $this;
    }

    public function getHeartbeat(): float
    {
        return $this->heartbeat;
    }

    public function setHeartbeat(float $heartbeat): static
    {
        $this->heartbeat = $heartbeat;
        return $this;
    }

    public function getWaitTimeout(): float
    {
        return $this->waitTimeout;
    }

    public function setWaitTimeout(float $waitTimeout): static
    {
        $this->waitTimeout = $waitTimeout;
        return $this;
    }

    public function getMaxIdleTime(): float
    {
        return $this->maxIdleTime;
    }

    public function setMaxIdleTime(float $maxIdleTime): static
    {
        $this->maxIdleTime = $maxIdleTime;
        return $this;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function setEvents(array $events): static
    {
        $this->events = $events;
        return $this;
    }
}