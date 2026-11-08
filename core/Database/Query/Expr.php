<?php

namespace Ryra\Core\Database\Query;

use Ryra\Core\Support\Queryable;

class Expr
{
    public static function coalesce(Queryable|string ...$args): AliasedQuery
    {
        [$query, $bindings] = static::mergeQueryAndBindings($args);
        return new AliasedQuery("coalesce($query)", $bindings);
    }

    public static function concat(Queryable|string ...$args): AliasedQuery
    {
        [$query, $bindings] = static::mergeQueryAndBindings($args);
        return new AliasedQuery("concat($query)", $bindings);
    }

    public static function if(Queryable|string $expr, Queryable|string $ifTrue, Queryable|string $ifFalse): Queryable
    {
        [$query, $bindings] = static::mergeQueryAndBindings([$expr, $ifTrue, $ifFalse]);
        return new QueryRaw("if($query)", $bindings);
    }

    public static function ifNull(Queryable|string $arg1, Queryable|string $arg2): Queryable
    {
        [$query, $bindings] = static::mergeQueryAndBindings([$arg1, $arg2]);
        return new QueryRaw("ifnull($query)", $bindings);
    }

    protected static function mergeQueryAndBindings(array $args): array
    {
        $bindings = [];
        $query = [];
        foreach ($args as $arg) {
            if ($arg instanceof Queryable) {
                $bindings = array_merge($bindings, $arg->getBindings());
            }
            $query[] = (string) $arg;
        }
        $query = implode(',', $query);
        return [
            $query,
            $bindings,
        ];
    }
}