<?php

namespace Ryra\Core\Collection;

class HighOderCollectionProxy
{
    public function __construct(protected Enumerable $collection, protected string $func) {}

    public function __get(string $property): mixed
    {
        return $this->collection->{$this->func}(function ($item) use ($property) {
            return $item[$property];
        });
    }

    public function __call(string $name, array $arguments): mixed
    {
        return $this->collection->{$this->func}(function ($item) use ($name, $arguments) {
            return $item->$name(...$arguments);
        });
    }
}