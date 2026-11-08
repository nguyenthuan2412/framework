<?php

namespace Ryra\Core\Database;

use Ryra\Core\Contracts\Database\Connection;
use Ryra\Core\Database\Query\QueryRaw;
use Ryra\Core\Database\Query\Builder;
use Ryra\Core\Support\Facade;
use Ryra\Core\App;

/**
 * @method static \Ryra\Core\Database\Query\Builder from(\Ryra\Core\Support\Queryable|string $table, ?string $alias = null)
 * @method static \Ryra\Core\Database\Query\Builder table(\Ryra\Core\Support\Queryable|string $table, ?string $alias = null)
 * @method static \Ryra\Core\Database\Query\Builder wrap(string $alias)
 * @method static ?array find(string $id, string $primaryKey = 'id')
 * @method static \Ryra\Core\Database\Query\Builder select(\Ryra\Core\Support\Queryable|string ...$columns)
 * @method static \Ryra\Core\Database\Query\Builder addSelect(\Ryra\Core\Support\Queryable|string ...$columns)
 * @method static \Ryra\Core\Database\Query\Builder selectRaw(string $query, array $bindings = [])
 * @method static \Ryra\Core\Database\Query\Builder addSelectRaw(string $query, array $bindings = [])
 * @method static int insert(iterable $fields)
 * @method static int insertOrIgnore(iterable $fields)
 * @method static int insertGetId(iterable $fields, string $primaryKey = 'id')
 * @method static int insertUsing(array $columns, \Closure|\Ryra\Core\Support\Queryable $query)
 * @method static int delete()
 * @method static int update(iterable $fields)
 * @method static \Ryra\Core\Collection\Collection get(array $columns = [], ?string $model = null, string $collection = \Ryra\Core\Collection\Collection::class)
 * @method static \Ryra\Core\Database\Query\Builder limit(int $limit, ?int $offset = null)
 * @method static mixed first()
 * @method static mixed value(string $column)
 * @method static \Ryra\Core\Collection\Collection pluck(string $firstColumn, ?string $secondColumn = null)
 * @method static \Ryra\Core\Database\Query\Builder offset(int $offset)
 * @method static \Ryra\Core\Database\Query\Builder groupBy(string ...$groupColumn)
 * @method static \Ryra\Core\Database\Query\Builder orderBy(\Ryra\Core\Database\Query\QueryRaw|string $column, string $order = 'asc')
 * @method static \Ryra\Core\Database\Query\Builder orderByDesc(string $column)
 * @method static \Ryra\Core\Database\Query\Builder orderByRaw(string $orderBy, array $bindings = [])
 * @method static \Ryra\Core\Database\Query\Builder reorder(string $column, string $order = 'asc')
 * @method static \Ryra\Core\Database\Query\Builder oldest(string $column = 'created_at')
 * @method static \Ryra\Core\Database\Query\Builder latest(string $column = 'created_at')
 * @method static int count(?string $column = null)
 * @method static mixed max(string $column)
 * @method static mixed min(string $column)
 * @method static mixed avg(string $column)
 * @method static mixed sum(string $column)
 * @method static bool exists()
 * @method static bool doesntExist()
 * @method static \Ryra\Core\Collection\LazyCollection cursor(array $columns = [], ?string $model = null, string $collection = \Ryra\Core\Collection\LazyCollection::class)
 * @method static \Ryra\Core\Database\Query\Builder where(string|array|Closure $columns, mixed $value = null, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder orWhere(mixed $columns, mixed $value = null, string $condition = '=')
 * @method static \Ryra\Core\Database\Query\Builder whereNull(string $column)
 * @method static \Ryra\Core\Database\Query\Builder orWhereNull(string $column)
 * @method static \Ryra\Core\Database\Query\Builder whereNot(mixed $columns, mixed $value = null, string $condition = '=')
 * @method static \Ryra\Core\Database\Query\Builder orWhereNot(mixed $columns, mixed $value = null, string $condition = '=')
 * @method static \Ryra\Core\Database\Query\Builder whereNotNull(string $column)
 * @method static \Ryra\Core\Database\Query\Builder orWhereNotNull(string $column)
 * @method static \Ryra\Core\Database\Query\Builder whereAny(array $columns, mixed $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder orWhereAny(array $columns, mixed $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Query\Builder whereAll(array $columns, mixed $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder orWhereAll(array $columns, mixed $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Query\Builder whereContains(string $column, mixed $value)
 * @method static \Ryra\Core\Database\Query\Builder orWhereContains(string $column, mixed $value)
 * @method static \Ryra\Core\Database\Query\Builder whereDate(string $column, string|\DateTimeInterface $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder orWhereDate(string $column, string|\DateTimeInterface $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Query\Builder whereMonth(string $column, string|\DateTimeInterface $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder orWhereMonth(string $column, string|\DateTimeInterface $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Query\Builder whereDay(string $column, string|\DateTimeInterface $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder orWhereDay(string $column, string|\DateTimeInterface $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Query\Builder whereYear(string $column, string|\DateTimeInterface $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder orWhereYear(string $column, string|\DateTimeInterface $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Query\Builder whereTime(string $column, string|\DateTimeInterface $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder orWhereTime(string $column, string|\DateTimeInterface $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Query\Builder whereIn(string $column, iterable|callable|\Ryra\Core\Support\Queryable $values, bool $isNot = false, string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder orWhereIn(string $column, iterable|callable|\Ryra\Core\Support\Queryable $values, bool $isNot = false, string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder whereNotIn(string $column, iterable|callable|\Ryra\Core\Support\Queryable $values, bool $isNot = false, string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder orWhereNotIn(string $column, iterable|callable|\Ryra\Core\Support\Queryable $values, bool $isNot = false, string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder whereColumn(string|array $first, string|array|null $second = null, string $operator = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder orWhereColumn(string|array $first, string|array|null $second = null, string $operator = '=')
 * @method static \Ryra\Core\Database\Query\Builder whereExists(callable|\Ryra\Core\Support\Queryable $callback, string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder orWhereExists(callable|\Ryra\Core\Support\Queryable $callback)
 * @method static \Ryra\Core\Database\Query\Builder whereNotExists(callable|\Ryra\Core\Support\Queryable $callback, string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder orWhereNotExists(callable|\Ryra\Core\Support\Queryable $callback)
 * @method static \Ryra\Core\Database\Query\Builder whereRaw(string $query, array $bindings = [], string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder orWhereRaw(string $query, array $bindings = [])
 * @method static \Ryra\Core\Database\Query\Builder join(string $table, string|callable $first, ?string $second = null, string $condition = '=', string $type = 'inner')
 * @method static \Ryra\Core\Database\Query\Builder leftJoin(string $table, string|callable $first, ?string $second = null, string $condition = '=')
 * @method static \Ryra\Core\Database\Query\Builder rightJoin(string $table, string|callable $first, ?string $second = null, string $condition = '=')
 * @method static \Ryra\Core\Database\Query\Builder joinSub(\Ryra\Core\Support\Queryable $table, string $alias, callable $callback, string $type = 'inner')
 * @method static \Ryra\Core\Database\Query\Builder leftJoinSub(\Ryra\Core\Support\Queryable $table, string $alias, callable $callback, string $type = 'inner')
 * @method static \Ryra\Core\Database\Query\Builder rightJoinSub(\Ryra\Core\Support\Queryable $table, string $alias, callable $callback, string $type = 'inner')
 * @method static \Ryra\Core\Database\Query\Builder union(\Ryra\Core\Support\Queryable $query)
 * @method static \Ryra\Core\Database\Query\Builder unionall(\Ryra\Core\Support\Queryable $query)
 * @method static \Ryra\Core\Database\Query\Builder having(string|\Ryra\Core\Support\Queryable $first, mixed $second, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder havingRaw(string $query, iterable $bindings = [], string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Query\Builder orHaving(string|\Ryra\Core\Support\Queryable $first, mixed $second, string $condition = '=')
 * @method static \Ryra\Core\Database\Query\Builder orHavingRaw(string $query, iterable $bindings = [])
 * @method static int increment(string|array $column, int|float $value = 1, array $fields = [])
 * @method static int incrementEach(array $fields)
 * @method static int decrement(string|array $column, int|float $value = 1, array $fields = [])
 * @method static int decrementEach(array $fields)
 * @method static int truncate()
 * @method static mixed transaction(callable $callback, $retries = 1)
 * @method static bool beginTransaction()
 * @method static bool commit()
 * @method static bool rollBack(bool $rollbackAll = false)
 * @method static \Ryra\Core\Pagination\Paginator paginate(int $perPage, ?int $page = null, ?string $pageName = null, ?int $total = null, ?string $model = null, string $collection = \Ryra\Core\Collection\Collection::class)
 * @method static \Ryra\Core\Pagination\Paginator fastPaginate(int $perPage, ?int $page = null, string $primaryKey = 'id', ?string $pageName = null, ?int $total = null, ?string $model = null,, string $collection = \Ryra\Core\Collection\Collection::class)
 * @method static \Ryra\Core\Database\Query\Builder when(bool $condition, callable $callback, ?callable $default = null)
 * @method static \Ryra\Core\Database\Query\Builder unless(bool $condition, callable $callback, ?callable $default = null)
 * @method static array getBindings()
 * @method static \Ryra\Core\Database\Query\Builder lock(string $lock)
 * @method static \Ryra\Core\Database\Query\Builder lockForUpdate()
 * @method static \Ryra\Core\Database\Query\Builder sharedLock()
 * @method static string toSql()
 * @method static string toRawSql(bool $pretty = false, Highlighter $highlighter = new NullHighlighter())
 * @method static array explain()
 * @method static string __toString()
 * @method static \Ryra\Core\Database\Query\Builder query()
 */
class DB extends Facade
{
    protected static function getFacadeAccessor(): string|object
    {
        return static::query();
    }

    public static function query(): Builder
    {
        return static::connection()->query();
    }

    public static function connection(?string $connection = null): Connection
    {
        return App::service('database')->getConnection($connection);
    }

    public static function raw(string $sql, array $bindings = []): QueryRaw
    {
        return new QueryRaw($sql, $bindings);
    }
}