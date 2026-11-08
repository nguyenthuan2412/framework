<?php

namespace Ryra\Core\Database\Query;

use Ryra\Core\Support\Queryable;

class Helper
{
    public static function column(Queryable|string $column, ?string $as = null): Queryable
    {
        $column = is_null($as) ? (string) $column : "{$column} as $as";
        $bindings = is_string($column) ? [] : $column->getBindings();
        return new QueryRaw($column, $bindings);
    }

    public static function value(mixed $value): Queryable
    {
        return new QueryRaw('?', [$value]);
    }
}