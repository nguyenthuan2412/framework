<?php

namespace Ryra\Core\Database\Query;

use Ryra\Core\Support\Queryable;

class QueryRaw implements Queryable
{
    public function __construct(public readonly string $sql, public readonly array $bindings = []) {}

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function toSql(): string
    {
        return $this->sql;
    }

    public function __toString(): string
    {
        return $this->toSql();
    }
}