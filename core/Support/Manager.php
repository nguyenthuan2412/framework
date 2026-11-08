<?php

namespace Ryra\Core\Support;

abstract class Manager
{
    protected array $drivers = [];

    abstract public function getDefaultDriver(): string;

    public function driver(?string $driver = null, array $params = []): mixed
    {
        if (is_null($driver)) {
            $driver = $this->getDefaultDriver();
        }

        if (is_null($method = $this->getMethodByDriver($driver))) {
            $driver = Str::studly($driver);
            $method = "create{$driver}Driver";
            $this->drivers[$driver] = $method;
        }

        return $this->$method(...$params);
    }

    protected function getMethodByDriver(string $driver): ?string
    {
        return $this->drivers[$driver] ?? null;
    }

    public function __call(string $method, array $arguments = [])
    {
        return $this->driver()->$method(...$arguments);
    }
}