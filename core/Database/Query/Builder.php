<?php

namespace Ryra\Core\Database\Query;

use Doctrine\SqlFormatter\{Highlighter, NullHighlighter, SqlFormatter};
use Ryra\Core\Contracts\Database\Connection;
use Ryra\Core\Database\Query\Traits\BuildsQueries;
use Ryra\Core\Database\Query\QueryRaw;
use Ryra\Core\Database\DB;
use Ryra\Core\Support\Traits\Conditionable;
use Ryra\Core\Support\Queryable;
use Ryra\Core\Support\Str;
use Ryra\Core\Collection\LazyCollection;
use Ryra\Core\Collection\Collection;
use Ryra\Core\Collection\Arr;
use Ryra\Core\Pagination\Paginator;
use Ryra\Core\Pagination\Cursor;
use Ryra\Core\Pagination\CursorPaginator;
use Ryra\Core\Macro\Macroable;
use Ryra\Core\App;
use DateTimeInterface;
use Closure;
use UnitEnum;

class Builder implements Queryable
{
    use Macroable {
        Macroable::__call as ___call;
    }

    use BuildsQueries, Conditionable;

    protected array $bindings = [
        'with' => [],
        'select' => [],
        'insert' => [],
        'update' => [],
        'delete' => [],
        'from' => [],
        'join' => [],
        'condition' => [],
        'groupBy' => [],
        'orderBy' => [],
        'union' => []
    ];

    protected array $fields = [];

    protected Queryable|string $table;

    protected string $alias = '';

    protected array $segments = [
        'insert' => false,
        'update' => false,
        'delete' => false,
        'with' => [],
        'select' => true,
        'join' => [],
        'condition' => [],
        'groupBy' => [],
        'having' => [],
        'orderBy' => [],
        'limit' => false,
        'offset' => false,
        'union' => [],
        'lock' => false
    ];

    protected array $pointerConditions = [];

    protected ?int $limit;

    protected ?int $offset;

    protected ?string $wrap = null;

    protected bool $distinct = false;

    public function __construct(protected Connection $connection)
    {
    }

    public function from(Queryable|string $table, ?string $alias = null): static
    {
        if ($alias) {
            $this->alias = $alias;
        }

        if ($table instanceof Queryable) {
            $this->addBindValues($table->getBindings(), 'from');
        }

        $this->table = $table;
        return $this;
    }

    public function table(Queryable|string $table, ?string $alias = null): static
    {
        return $this->from($table, $alias);
    }

    public function alias(string $alias): static
    {
        $this->alias = $alias;
        return $this;
    }

    public function wrap(string $alias): static
    {
        $this->wrap = $alias;
        return $this;
    }

    public function find(string $id, string $primaryKey = 'id'): ?array
    {
        $this->segments['condition'] = [];
        $this->clearBindings('condition');
        return $this->where($primaryKey, $id)->first();
    }

    public function select(Queryable|string ...$columns): static
    {
        $this->fields = [];
        $this->clearBindings('select');
        return $this->addSelect(...$columns);
    }

    public function addSelect(Queryable|string ...$columns): static
    {
        foreach ($columns as $column) {
            $this->fields[] = $column;
            if (is_object($column)) {
                $this->addBindValues($column->getBindings(), 'select');
            }
        }
        return $this;
    }

    public function selectRaw(string $query, array $bindings = []): static
    {
        $queryRaw = new QueryRaw($query, $bindings);
        return $this->select($queryRaw);
    }

    public function addSelectRaw(string $query, array $bindings = []): static
    {
        $queryRaw = new QueryRaw($query, $bindings);
        return $this->addSelect($queryRaw);
    }

    public function distinct(): static
    {
        $this->distinct = true;
        return $this;
    }

    public function insert(iterable $fields): int
    {
        $fields = iterator_to_list($fields);
        $this->fields = $fields;
        $this->addBindValues($fields, 'insert');
        return $this->run('insert', function (Connection $connection) {
            return $connection->insert($this->toSql(), $this->getBindings());
        });
    }

    public function insertOrIgnore(iterable $fields): int
    {
        $fields = iterator_to_list($fields);
        $this->fields = $fields;
        $this->addBindValues($fields, 'insert');
        return $this->run('insert', function (Connection $connection) {
            $sql = $this->toSql();
            $insert = 'insert';
            $position = strpos($sql, $insert);
            if ($position !== false) {
                $sql = substr_replace($sql, 'insert ignore', $position, strlen($insert));
            }
            return $connection->insert($sql, $this->getBindings());
        });
    }

    public function insertGetId(iterable $fields, string $primaryKey = 'id'): int
    {
        $fields = iterator_to_list($fields);
        $this->fields = $fields;
        $this->addBindValues($fields, 'insert');
        return $this->run('insert', function (Connection $connection) use ($primaryKey) {
            return $connection->insertGetId($this->toSql(), $primaryKey, $this->getBindings());
        });
    }

    public function insertUsing(array $columns, \Closure|Queryable $query): int
    {
        if ($query instanceof \Closure) {
            $callback = $query;
            $query = new static($this->connection);
            $callback($query);
        }
        return $this->run('insert', function (Connection $connection) use ($query, $columns) {
            $columns = implode(', ', $this->backtick($columns));
            return $connection->insert(
                "insert into {$this->getTable()} ($columns) $query",
                $query->getBindings()
            );
        });
    }

    public function updateOrInsert(iterable $conditions, iterable $fields): bool
    {
        $query = clone $this;
        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }

        $item = $query->first();
        if (empty($item)) {
            $fieldInsert = array_merge(
                iterator_to_list($fields),
                iterator_to_list($conditions),
            );
            return $this->insert($fieldInsert);
        }

        return $query->update($fields);
    }

    public function delete(): int
    {
        return $this->run('delete', function (Connection $connection) {
            return $connection->delete($this->toSql(), $this->getBindings());
        });
    }

    public function update(iterable $fields): int
    {
        $this->fields = iterator_to_list($fields);
        foreach ($fields as $value) {
            if ($value instanceof QueryRaw) {
                $this->addBindValues($value->getBindings(), 'update');
                continue;
            }
            $this->addBindValue($value, 'update');
        }
        return $this->run('update', function (Connection $connection) {
            return $connection->update($this->toSql(), $this->getBindings());
        });
    }

    public function get(array $columns = [], ?string $model = null, string $collection = Collection::class): Collection
    {
        $results = $this->run('select', function (Connection $connection) {
            return $connection->select($this->toSql(), $this->getBindings());
        });

        $collection = new $collection($results);
        if (!empty($columns)) {
            $collection->only(...$columns);
        }

        if (is_null($model)) {
            return $collection;
        }

        return $collection->map(fn ($item) => (new $model())->setData($item));
    }

    public function limit(int $limit, ?int $offset = null): static
    {
        $this->segments['limit'] = true;
        $this->limit = $limit;
        if ($offset !== null) {
            $this->offset($offset);
        }
        return $this;
    }

    public function first(): mixed
    {
        return $this->limit(1)->get()->first();
    }

    public function value(string $column): mixed
    {
        return $this->first()[$column] ?? null;
    }

    public function pluck(string $firstColumn, ?string $secondColumn = null): Collection
    {
        return $this->get()->pluck($firstColumn, $secondColumn);
    }

    public function offset(int $offset): static
    {
        $this->segments['offset'] = true;
        $this->offset = $offset;
        return $this;
    }

    public function groupBy(string|Queryable ...$groupColumn): static
    {
        foreach ($groupColumn as $sql) {
            if (is_string($sql)) {
                $this->segments['groupBy'][] = $sql;
            } else {
                $this->segments['groupBy'][] = $sql;
                $this->addBindValues($sql->getBindings(), 'groupBy');
            }
        }
        return $this;
    }

    public function groupByRaw(string $sql, array $bindings = []): static
    {
        $this->segments['groupBy'][] = new QueryRaw($sql);
        $this->addBindValues($bindings, 'groupBy');
        return $this;
    }

    public function hasGroupBy(): bool
    {
        return !empty($this->segments['groupBy']);
    }

    public function orderBy(QueryRaw|string $column, string $order = 'asc'): static
    {
        $order = strtolower($order);
        if (!in_array($order, ['asc', 'desc'], true)) {
            $order = 'asc';
        }
        if (is_string($column)) {
            $this->segments['orderBy'][$column] = $order;
        } else {
            $this->segments['orderBy'][] = $column;
        }
        return $this;
    }

    public function orderByDesc(string $column): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function orderByRaw(string $orderBy, array $bindings = []): static
    {
        return $this->orderBy(new QueryRaw($orderBy, $bindings));
    }

    public function reorder(string $column, string $order = 'asc'): static
    {
        $this->segments['orderBy'] = [];
        return $this->orderBy($column, $order);
    }

    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column);
    }

    public function latest(string $column = 'created_at'): static
    {
        return $this->orderByDesc($column);
    }

    public function count(?string $column = null): int
    {
        if (is_null($column)) {
            $column = '*';
        } else {
            $column = $this->getColumn($column);
        }
        $result = $this->selectRaw("count($column)")->first();
        return !empty($result) ? array_values($result)[0] : 0;
    }

    public function max(string $column): mixed
    {
        $record = $this->selectRaw("max($column)")->first();
        if (!empty($record)) {
            return array_values($record)[0];
        }

        return 0;
    }

    public function min(string $column): mixed
    {
        $record = $this->selectRaw("min($column)")->first();
        if (!empty($record)) {
            return array_values($record)[0];
        }

        return 0;
    }

    public function avg(string $column): mixed
    {
        $record = $this->selectRaw("avg($column)")->first();
        if (!empty($record)) {
            return array_values($record)[0];
        }

        return 0;
    }

    public function sum(string $column): mixed
    {
        $record = $this->selectRaw("sum($column)")->first();
        if (!empty($record)) {
            return array_values($record)[0] ?? 0;
        }

        return 0;
    }

    public function exists(): bool
    {
        return !empty($this->first());
    }

    public function doesntExist(): bool
    {
        return empty($this->first());
    }

    public function cursor(array $columns = [], ?string $model = null, string $collection = LazyCollection::class): LazyCollection
    {
        $source = $this->run('select', function (Connection $connection) use ($model, $columns) {
            $statement = $connection->statement($this->toSql(), $this->getBindings());
            return function () use ($statement, $model, $columns) {
                while ($row = $statement->fetch()) {
                    if (!empty($columns)) {
                        $row = Arr::only($row, $columns);
                    }

                    if (is_null($model)) {
                        yield $row;
                    } else {
                        yield (new $model())->setData($row);
                    }
                }
            };
        });
        return new $collection($source);
    }

    public function where(
        string|array|callable $columns,
        mixed $value = null,
        string $condition = '=',
        string $logicalOperator = 'and'
    ): static
    {
        if (is_array($columns)) {
            return $this->addNestedCondition(function () use ($columns, $logicalOperator) {
                foreach ($columns as $column => $value) {
                    if (is_array($value)) {
                        $this->addCondition($value[0], $value[1], $value[2], $logicalOperator);
                        continue;
                    }
                    $this->addCondition($column, '=', $value, $logicalOperator);
                }
            }, $logicalOperator);
        } elseif (is_string($columns)) {
            $this->addCondition($columns, $condition, $value, $logicalOperator);
        } else {
            $this->addNestedCondition($columns, $logicalOperator);
        }

        return $this;
    }

    public function orWhere(mixed $columns, mixed $value = null, string $condition = '='): static
    {
        return $this->where($columns, $value, $condition, 'or');
    }

    public function whereNull(string $column): static
    {
        $column = $this->getColumn($column);
        return $this->whereRaw("$column is null");
    }

    public function whereNot(mixed $columns, mixed $value = null, string $condition = '='): static
    {
        return $this->where($columns, $value, $condition, 'and not');
    }

    public function orWhereNot(mixed $columns, mixed $value = null, string $condition = '='): static
    {
        return $this->where($columns, $value, $condition, 'or not');
    }

    public function whereNotNull(string $column): static
    {
        $column = $this->getColumn($column);
        return $this->whereRaw("$column is not null");
    }

    public function orWhereNull(string $column): static
    {
        $column = $this->getColumn($column);
        return $this->orWhereRaw("$column is null");
    }

    public function orWhereNotNull(string $column): static
    {
        $column = $this->getColumn($column);
        return $this->orWhereRaw("$column is not null");
    }

    public function whereAny(
        array $columns,
        mixed $value,
        string $condition = '=',
        string $logicalOperator = 'and'
    ): static
    {
        return $this->where(function (Builder $query) use (&$columns, &$value, &$condition) {
            foreach ($columns as $column) {
                $query->orWhere($column, $value, $condition);
            }
        }, $logicalOperator);
    }

    public function orWhereAny(array $columns, mixed $value, string $condition = '='): static
    {
        return $this->whereAny($columns, $value, $condition, 'or');
    }

    public function whereAll(array $columns, mixed $value, string $condition = '=', string $logicalOperator = 'and'): static
    {
        return $this->where(function (Builder $query) use (&$columns, $value, &$condition) {
            foreach ($columns as $column) {
                $query->where($column, $value, $condition);
            }
        }, $logicalOperator);
    }

    public function orWhereAll(array $columns, mixed $value, string $condition = '='): static
    {
        return $this->whereAll($columns, $value, $condition, 'or');
    }

    public function whereContains(string $column, mixed $value): static
    {
        return $this->where($column, "%$value%", 'like');
    }

    public function orWhereContains(string $column, mixed $value): static
    {
        return $this->orWhere($column, "%$value%", 'like');
    }

    public function whereDate(
        string $column,
        string|DateTimeInterface $value,
        string $condition = '=',
        string $logicalOperator = 'and'
    ): static
    {
        $value = $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
        $column = $this->getColumn($column);
        return $this->whereRaw("date($column) $condition ?", [$value], $logicalOperator);
    }

    public function orWhereDate(string $column, string|DateTimeInterface $value, string $condition = '='): static
    {
        return $this->whereDate($column, $value, $condition, 'or');
    }

    public function whereMonth(
        string $column,
        string|DateTimeInterface $value,
        string $condition = '=',
        string $logicalOperator = 'and'
    ): static
    {
        $value = $value instanceof DateTimeInterface ? $value->format('m') : $value;
        $column = $this->getColumn($column);
        return $this->whereRaw("month($column) $condition ?", [$value], $logicalOperator);
    }

    public function orWhereMonth(string $column, string|DateTimeInterface $value, string $condition = '='): static
    {
        return $this->whereMonth($column, $value, $condition, 'or');
    }

    public function whereDay(
        string $column,
        string|DateTimeInterface $value,
        string $condition = '=',
        string $logicalOperator = 'and'
    ): static
    {
        $value = $value instanceof DateTimeInterface ? $value->format('d') : $value;
        $column = $this->getColumn($column);
        return $this->whereRaw("day($column) $condition ?", [$value], $logicalOperator);
    }

    public function orWhereDay(string $column, string|DateTimeInterface $value, string $condition = '='): static
    {
        return $this->whereDay($column, $value, $condition, 'or');
    }

    public function whereYear(
        string $column,
        string|DateTimeInterface $value,
        string $condition = '=',
        string $logicalOperator = 'and'
    ): static
    {
        $value = $value instanceof DateTimeInterface ? $value->format('Y') : $value;
        $column = $this->getColumn($column);
        return $this->whereRaw("year($column) $condition ?", [$value], $logicalOperator);
    }

    public function orWhereYear(string $column, string|DateTimeInterface $value, string $condition = '='): static
    {
        return $this->whereYear($column, $value, $condition, 'or');
    }

    public function whereTime(
        string $column,
        string|DateTimeInterface $value,
        string $condition = '=',
        string $logicalOperator = 'and'
    ): static
    {
        $value = $value instanceof DateTimeInterface ? $value->format('H:i:s') : $value;
        $column = $this->getColumn($column);
        return $this->whereRaw("time($column) $condition ?", [$value], $logicalOperator);
    }

    public function orWhereTime(
        string $column,
        string|DateTimeInterface $value,
        string $condition = '='
    ): static
    {
        return $this->whereTime($column, $value, $condition, 'or');
    }

    public function whereIn(
        string $column,
        iterable|callable|Queryable $values,
        bool $isNot = false,
        string $logicalOperator = 'and'
    ): static
    {
        $column = $this->getColumn($column);
        $logicalOperator2 = $isNot ? 'not in' : 'in';
        if (is_iterable($values)) {
            $values = iterator_to_list($values);

            if (!empty($values)) {
                $placeholders = '?';
                foreach (array_slice($values, 1) as $_) {
                    $placeholders .= ", ?";
                }
                return $this->whereRaw(
                    "$column $logicalOperator2 ($placeholders)",
                    $values,
                    $logicalOperator
                );
            }

            return $this->whereRaw('0 = 1');
        }

        if (is_callable($values)) {
            $builder = new static($this->connection);
            $values($builder);
            $values = $builder;
        }

        return $this->whereRaw(
            "$column $logicalOperator2 ({$values->toSql()})",
            $values->getBindings(),
            $logicalOperator
        );
    }

    public function orWhereIn(string $column, iterable|callable|Queryable $values): static
    {
        return $this->whereIn($column, $values, false, 'or');
    }

    public function whereNotIn(string $column, iterable|callable|Queryable $values): static
    {
        return $this->whereIn($column, $values, true, 'and');
    }
    
    public function orWhereNotIn(string $column, iterable|callable|Queryable $values): static
    {
        return $this->whereIn($column, $values, true, 'not');
    }

    public function whereBetween(string $column, array $values, string $logicalOperator = 'and'): static
    {
        $column = $this->getColumn($column);
        return $this->whereRaw("$column between ? and ?", $values, $logicalOperator);
    }

    public function orWhereBetween(string $column, array $values): static
    {
        return $this->whereRaw("$column between ? and ?", $values, 'or');
    }

    public function whereColumn(
        string|array $first,
        string|array|null $second = null,
        string $operator = '=',
        string $logicalOperator = 'and'
    ): static
    {
        if (is_array($first)) {
            return $this->addNestedCondition(function () use ($first) {
                foreach ($first as $key => $value) {
                    if (is_array($value)) {
                        if (count($value) === 3) {
                            $this->whereRaw("{$this->getColumn($value[0])} {$value[1]} {$this->getColumn($value[2])}");
                            continue;
                        }
                        $this->whereRaw("{$this->getColumn($value[0])} = {$this->getColumn($value[1])}");
                        continue;
                    }

                    $this->whereRaw("{$this->getColumn($key)} = {$this->getColumn($value)}");
                }
            }, $logicalOperator);
        }

        return $this->whereRaw(
            "{$this->getColumn($first)} $operator {$this->getColumn($second)}",
            logicalOperator: $logicalOperator
        );
    }

    public function orWhereColumn(string|array $first, string|array|null $second = null, string $operator = '='): static
    {
        return $this->whereColumn($first, $second, $operator, 'or');
    }

    public function whereExists(callable|Queryable $callback, string $logicalOperator = 'and'): static
    {
        if (is_callable($callback)) {
            $builder = new static($this->connection);
            $callback($builder);
        } else {
            $builder = $callback;
        }
        return $this->whereRaw("exists ({$builder->toSql()})", $builder->getBindings(), $logicalOperator);
    }

    public function whereNotExists(callable|Queryable $callback, string $logicalOperator = 'and'): static
    {
        if (is_callable($callback)) {
            $builder = new static($this->connection);
            $callback($builder);
        } else {
            $builder = $callback;
        }
        return $this->whereRaw("not exists ({$builder->toSql()})", $builder->getBindings(), $logicalOperator);
    }

    public function orWhereExists(callable|Queryable $callback): static
    {
        return $this->whereExists($callback, 'or');
    }

    public function orWhereNotExists(callable|Queryable $callback): static
    {
        return $this->whereNotExists($callback, 'or');
    }

    public function whereRaw(string $query, array $bindings = [], string $logicalOperator = 'and'): static
    {
        $queryRaw = new QueryRaw($query, $bindings);
        $this->addConditionRaw($queryRaw, $logicalOperator);
        return $this;
    }

    public function orWhereRaw(string $query, array $bindings = []): static
    {
        return $this->whereRaw($query, $bindings, 'or');
    }

    public function join(
        string $table,
        string|callable $first,
        ?string $second = null,
        string $condition = '=',
        string $type = 'inner'
    ): static
    {
        if (is_string($first)) {
            $table = $this->getTable($table, true);
            $this->segments['join'][$table] = [
                "$type join",
                $this->getColumn($first),
                $this->getColumn($second),
                $condition
            ];
            return $this;
        }

        $joinClause = new JoinClause($this->connection);
        $joinClause->from($table);
        $first($joinClause);
        $this->segments['join'][$table] = ["$type join", (string) $joinClause];
        $this->addBindValues($joinClause->getBindings(), 'join');
        return $this;
    }

    public function leftJoin(string $table, string|callable $first, ?string $second = null, string $condition = '='): static
    {
        return $this->join($table, $first, $second, $condition, 'left');
    }

    public function rightJoin(string $table, string|callable $first, ?string $second = null, string $condition = '='): static
    {
        return $this->join($table, $first, $second, $condition, 'right');
    }

    public function joinSub(Queryable $table, string $alias, callable $callback, string $type = 'inner'): static
    {
        $joinClause = new JoinClause($this->connection);
        $joinClause->from($table);
        $joinClause->wrap($alias);
        $callback($joinClause);
        $this->segments['join'][$alias] = ["$type join", (string) $joinClause];
        $this->addBindValues($joinClause->getBindings(), 'join');
        return $this;
    }

    public function leftJoinSub(Queryable $table, string $alias, callable $callback): static
    {
        return $this->joinSub($table, $alias, $callback, 'left');
    }

    public function rightJoinSub(Queryable $table, string $alias, callable $callback): static
    {
        return $this->joinSub($table, $alias, $callback, 'right');
    }

    public function union(Queryable $query): static
    {
        $this->addBindValues($query->getBindings(), 'union');
        $this->segments['union'][] = ['union', (string)$query];
        return $this;
    }
    
    public function unionall(Queryable $query): static
    {
        $this->addBindValues($query->getBindings(), 'union');
        $this->segments['union'][] = ['union all', (string)$query];
        return $this;
    }

    public function having(string|callable|Queryable $first, mixed $second, string $condition = '=', string $logicalOperator = 'and'): static
    {
        $this->addBindValue($second, 'having');
        if (is_string($first)) {
            $first = $this->getColumn($first);
        } elseif (is_callable($first)) {

        } else {
            $this->addBindValues($first->getBindings(), 'having');
            $first = (string) $first;
        }

        $this->segments['having'][] = [" $logicalOperator ", "$first $condition ?"];
        return $this;
    }

    public function havingRaw(string $query, iterable $bindings = [], string $logicalOperator = 'and'): static
    {
        $this->addBindValues($bindings, 'having');
        $this->segments['having'][] = [" $logicalOperator ", $query];
        return $this;
    }

    public function orHaving(string|Queryable $first, mixed $second, string $condition = '='): static
    {
        return $this->having($first, $second, $condition, 'or');
    }

    public function orHavingRaw(string $first, mixed $second, string $condition = '='): static
    {
        return $this->havingRaw($first, $second, $condition, 'or');
    }

    public function withExpression(string $alias, callable|Queryable $queryOrCallback): static
    {
        if (is_callable($queryOrCallback)) {
            $query = new static($this->connection);
            $queryOrCallback($query);
        }
        $this->segments['with'][] = ['with', $alias, $query->toSql()];
        $this->addBindValues($query->getBindings(), 'with');
        return $this;
    }

    public function increment(string|array $column, int|float $value = 1, array $fields = []): int
    {
        $column = $this->getColumn($column);
        return $this->update([new QueryRaw("$column = $column + ?", [$value]), ...$fields]);
    }

    public function incrementEach(array $fields): int
    {
        $updateFields = [];
        foreach ($fields as $column => $value) {
            $column = $this->getColumn($column);
            $updateFields[] = new QueryRaw("$column = $column + ?", [$value]);
        }
        return $this->update($updateFields);
    }

    public function decrement(string|array $column, int|float $value = 1, array $fields = []): int
    {
        $column = $this->getColumn($column);
        return $this->update([new QueryRaw("$column = $column - ?", [$value]), ...$fields]);
    }

    public function decrementEach(array $fields): int
    {
        $updateFields = [];
        foreach ($fields as $column => $value) {
            $column = $this->getColumn($column);
            $updateFields[] = new QueryRaw("$column = $column - ?", [$value]);
        }
        return $this->update($updateFields);
    }

    public function truncate(): int
    {
        return $this->run('truncate', function (Connection $connection) {
            return $connection->statement("truncate table {$this->getTable()}")->rowCount();
        });
    }

    public function transaction(callable $callback, $retries = 1): mixed
    {
        return $this->connection->transaction($callback, $retries);
    }

    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->connection->commit();
    }

    public function rollBack(bool $rollbackAll = false): bool
    {
        return $this->connection->rollBack($rollbackAll);
    }

    public function paginate(
        int $perPage,
        ?int $page = null,
        ?string $pageName = null,
        ?int $total = null,
        ?string $model = null,
        string $collection = Collection::class
    ): Paginator
    {
        $countBuilder = clone $this;

        if ($countBuilder->hasGroupBy()) {
            $total = DB::from($countBuilder, 'temp')->count();
        } else {
            $total = $countBuilder->count();
        }

        if (is_null($page)) {
            $page = App::service('request')->input('page', 1);
        }
        $offset = ($page - 1) * $perPage;
        $data = $this
            ->limit($perPage, $offset)
            ->get(model: $model, collection: $collection);

        return App::make(Paginator::class, [
            'data' => $data,
            'perPage' => $perPage,
            'page' => $page,
            'pageName' => $pageName,
            'total' => $total,
            'from' => $offset
        ]);
    }

    public function fastPaginate(
        int $perPage,
        ?int $page = null,
        string $primaryKey = 'id',
        ?string $pageName = null,
        ?int $total = null,
        ?string $model = null,
        string $collection = Collection::class
    ): Paginator
    {
        if (is_null($page)) {
            $page = App::service('request')->input('page', 1);
        }

        $countBuilder = clone $this;
        $total = $countBuilder->count();

        $offset = ($page - 1) * $perPage;
        $subQueryBuilder = clone $this;
        $subQueryBuilder
            ->select($primaryKey)
            ->limit($perPage, $offset);

        $selectBindings = $this->bindings['select'];
        $this->clearBindings();
        $this->bindings['select'] = $selectBindings;

        $lockSegment = $this->segments['lock'];
        $selectSegment = $this->segments['select'];
        $this->clearSegmentsSql();
        $this->segments['lock'] = $lockSegment;
        $this->segments['select'] = $selectSegment;

        $table = &$this->table;
        $data = $this->joinSub($subQueryBuilder, 'sub_query', function (JoinClause $join) use ($primaryKey, &$table) {
            $join->on("sub_query.$primaryKey", "$table.{$primaryKey}");
        })->get(model: $model, collection: $collection);

        return App::make(Paginator::class, [
            'data' => $data,
            'perPage' => $perPage,
            'page' => $page,
            'pageName' => $pageName,
            'total' => $total,
            'from' => $offset
        ]);
    }

    /**
     * Phân trang theo con trỏ (keyset pagination) tương tự Laravel.
     *
     * Không dùng OFFSET nên hiệu năng ổn định trên tập dữ liệu lớn, đổi lại chỉ
     * đi tới/lui được (không nhảy tới trang bất kỳ). Yêu cầu có order by trên các
     * cột định danh; nếu chưa có sẽ tự order theo $defaultOrderColumn.
     */
    public function cursorPaginate(
        int $perPage,
        ?string $cursorName = null,
        ?string $cursor = null,
        string $defaultOrderColumn = 'id',
        ?string $model = null,
        string $collection = Collection::class
    ): CursorPaginator
    {
        $cursorName ??= 'cursor';

        if (is_null($cursor)) {
            $cursor = App::service('request')->input($cursorName);
        }
        $cursor = Cursor::fromEncoded($cursor);

        $orders = $this->ensureCursorOrders($defaultOrderColumn);

        // Cursor trỏ về trang trước: đảo chiều order để lấy ngược, rồi CursorPaginator
        // sẽ đảo lại kết quả về đúng thứ tự hiển thị.
        if (!is_null($cursor) && $cursor->pointsToPreviousItems()) {
            foreach ($orders as &$order) {
                $order['direction'] = $order['direction'] === 'asc' ? 'desc' : 'asc';
            }
            unset($order);
        }

        $this->segments['orderBy'] = [];
        foreach ($orders as $order) {
            $this->orderBy($order['column'], $order['direction']);
        }

        if (!is_null($cursor)) {
            $this->applyCursorConditions($orders, $cursor);
        }

        // Lấy dư 1 bản ghi để biết còn trang kế hay không.
        $data = $this->limit($perPage + 1)->get(model: $model, collection: $collection);

        return new CursorPaginator(
            $data,
            $perPage,
            array_column($orders, 'alias'),
            $cursor,
            $cursorName
        );
    }

    /**
     * Chuẩn hoá danh sách order thành các cột định danh cho cursor pagination.
     * Bỏ qua order dạng QueryRaw (không thể keyset). Nếu chưa có order nào thì
     * mặc định order tăng dần theo $defaultOrderColumn.
     *
     * @return array<int, array{column: string, alias: string, direction: string}>
     */
    protected function ensureCursorOrders(string $defaultOrderColumn): array
    {
        $orders = [];
        foreach ($this->segments['orderBy'] as $column => $order) {
            if ($order instanceof QueryRaw || !is_string($column)) {
                continue;
            }
            $orders[] = [
                'column' => $column,
                'alias' => Str::afterLast($column, '.'),
                'direction' => $order,
            ];
        }

        if (empty($orders)) {
            $orders[] = [
                'column' => $defaultOrderColumn,
                'alias' => Str::afterLast($defaultOrderColumn, '.'),
                'direction' => 'asc',
            ];
        }

        return $orders;
    }

    /**
     * Dựng điều kiện where dạng keyset từ cursor. Với order (c0 d0, c1 d1, ...):
     *   (c0 OP0 v0) OR (c0 = v0 AND ((c1 OP1 v1) OR (c1 = v1 AND ...)))
     *
     * @param array<int, array{column: string, alias: string, direction: string}> $orders
     */
    protected function applyCursorConditions(array $orders, Cursor $cursor): void
    {
        $addConditions = function (Builder $builder, int $i) use (&$addConditions, $orders, $cursor) {
            $order = $orders[$i];
            $comparison = $order['direction'] === 'asc' ? '>' : '<';
            $value = $cursor->parameter($order['alias']);

            $builder->where(function (Builder $builder) use (&$addConditions, $orders, $order, $comparison, $value, $i) {
                $builder->where($order['column'], $value, $comparison);

                if ($i < count($orders) - 1) {
                    $builder->orWhere(function (Builder $builder) use (&$addConditions, $order, $value, $i) {
                        $builder->where($order['column'], $value);
                        $addConditions($builder, $i + 1);
                    });
                }
            });
        };

        $addConditions($this, 0);
    }

    protected function clearBindings(?string $field = null): void
    {
        if (is_null($field)) {
            $this->bindings = [
                'with' => [],
                'select' => [],
                'insert' => [],
                'update' => [],
                'delete' => [],
                'join' => [],
                'condition' => [],
                'orderBy' => [],
                'union' => []
            ];
        } else {
            $this->bindings[$field] = [];
        }
    }

    protected function clearSegmentsSql(): void
    {
        $this->segments = [
            'insert' => false,
            'update' => false,
            'delete' => false,
            'with' => [],
            'select' => true,
            'join' => false,
            'condition' => [],
            'groupBy' => [],
            'having' => [],
            'orderBy' => [],
            'limit' => false,
            'offset' => false,
            'lock' => false
        ];
    }

    protected function addConditionRaw(QueryRaw $queryRaw, string $logicalOperator = 'and'): void
    {
        $logicalOperator = $this->renderLogicalOperator($logicalOperator);
        if (empty($this->pointerConditions)) {
            $pointerCondition = &$this->segments['condition'];
        } else {
            $pointerCondition = &$this->pointerConditions[count($this->pointerConditions) - 1];
        }
        $pointerCondition[] = "$logicalOperator$queryRaw";
        $this->addBindValues($queryRaw->getBindings(), 'condition');
    }

    protected function addCondition(string $column, string $condition, mixed $value, string $logicalOperator = 'and'): void
    {
        $logicalOperator = $this->renderLogicalOperator($logicalOperator);
        if (empty($this->pointerConditions)) {
            $pointerConditions = &$this->segments['condition'];
        } else {
            $pointerConditions = &$this->pointerConditions[count($this->pointerConditions) - 1];
        }

        if ($value instanceof Queryable) {
            $val = "({$value->toSql()})";
            $this->addBindValues($value->getBindings(), 'condition');
        } else if (is_callable($value)) {
            $query = new static($this->connection);
            $value($query);
            $val = "({$query->toSql()})";
            $this->addBindValues($query->getBindings(), 'condition');
        } else {
            $val = '?';
            $this->addBindValue($value, 'condition');
        }

        $column = "$logicalOperator{$this->getColumn($column)}";
        $pointerConditions[] = "$column $condition $val";
    }

    protected function addNestedCondition(callable $callback, string $logicalOperator = 'and'): static
    {
        $groupCondition = [];
        if (empty($this->pointerConditions)) {
            $this->pointerConditions[] = &$groupCondition;
            $logicalOperator = $this->renderLogicalOperator($logicalOperator, true);
            $groupCondition[] = "$logicalOperator(%s)";
            $this->segments['condition'][] = &$groupCondition;
        } else {
            $lastPointer = &$this->pointerConditions[count($this->pointerConditions) - 1];
            $lastPointer[] = &$groupCondition;
            $this->pointerConditions[] = &$lastPointer[count($lastPointer) - 1];
            $logicalOperator = $this->renderLogicalOperator($logicalOperator, true);
            $groupCondition[] = "$logicalOperator(%s)";
        }

        $callback($this);
        array_pop($this->pointerConditions);
        return $this;
    }

    protected function renderLogicalOperator(string $logicalOperator): ?string
    {
        // Các điều kiện where đang không ở trong 1 group nào.
        if (empty($this->pointerConditions)) {
            // Nếu cũng chưa có điều kiện where nào cả thì không nối and hoặc or.
            if (empty($this->segments['condition'])) {
                if (str_contains($logicalOperator, 'not')) {
                    return 'not ';
                }

                return null;
            }
            return " $logicalOperator ";
        }

        // Nếu group condition được tạo ngay sau lệnh where thì không có logical operator.
        if (empty($this->segments['condition'])) {
            return null;
        }

        $countPointerCondition = count($this->pointerConditions);
        $lastPointerCondition = $this->pointerConditions[$countPointerCondition - 1];
        $countLastPointerCondition = count($lastPointerCondition);
        // Nếu đang ở lệnh mở group condition.
        if ($countLastPointerCondition === 0) {
            // Nếu lệnh mở group condition này đang ở trong một group condition khác.
            if ($countPointerCondition >= 2) {
                $prevPointerCondition = $this->pointerConditions[$countPointerCondition - 2];
                if (count($prevPointerCondition) <= 2) {
                    return null;
                }

                return " $logicalOperator ";
            }
        } elseif ($countLastPointerCondition === 1) {
            return null;
        }

        return " $logicalOperator ";
    }

    protected function run(string $typeDML, callable $callback): mixed
    {
        $this->setTypeDML($typeDML);
        $results = $callback($this->connection);
        return $results;
    }

    protected function buildWith()
    {
        $withs = [];
        foreach ($this->segments['with'] as $with) {
            $withs[] = "{$with[0]} {$this->getTable($with[1])} as ({$with[2]})";
        };

        return implode(', ', $withs);
    }

    protected function buildSelect(): string
    {
        if (empty($this->fields)) {
            $fields = '*';
        } else {
            $fields = array_map(function ($field) {
                return is_string($field) ? $this->getColumn($field) : (string)$field;
            }, $this->fields);
            $fields = implode(', ', $fields);
        }
        $distinct = $this->distinct ? ' distinct ' : ' ';
        return "select{$distinct}$fields from {$this->getTable()}";
    }

    protected function buildInsert(): string
    {
        $firstKey = key($this->fields);

        // Trg hợp insert nhiều bản ghi
        if (is_array($this->fields[$firstKey])) {
            $fields = [];
            foreach ($this->fields as $field) {
                $fields[] = '(' . substr(str_repeat('?, ', count($field)), 0, -2) . ')';
            }
            $fields = implode(', ', $fields);
            $columns = array_keys($this->fields[$firstKey]);
        }

        // Trg hợp insert 1 bản ghi
        else {
            $fields = '(' . substr(str_repeat('?, ', count($this->fields)), 0, -2) . ')';
            $columns = array_keys($this->fields);
        }

        $columns = implode(', ', $this->backtick($columns));
        return "insert into {$this->getTable()} ($columns) values $fields";
    }

    protected function buildUpdate(): string
    {
        $sets = '';
        foreach ($this->fields as $key => $value) {
            if (empty($sets)) {
                $sets .= $value instanceof QueryRaw ? (string)$value : "{$this->getColumn($key)} = ?";
            } else {
                $sets .= $value instanceof QueryRaw ? ", $value" : ", {$this->getColumn($key)} = ?";
            }
        }
        return "update {$this->getTable()} set $sets";
    }

    protected function buildDelete(): string
    {
        return "delete from {$this->getTable()}";
    }

    protected function buildJoin(): string
    {
        $segments = [];
        foreach ($this->segments['join'] as $table => $join) {
            if (count($join) == 4) {
                $segments[] = "{$join[0]} $table on {$join[1]} {$join[3]} {$join[2]}";
            } else {
                $segments[] = "{$join[0]} {$join[1]}";
            }
        }

        return implode(' ', $segments);
    }

    protected function buildCondition(array $conditions = [], string $prefix = 'where '): ?string
    {
        if (empty($conditions)) {
            $conditions = $this->segments['condition'];
            $command = $prefix;
        } else {
            $command = '';
        }

        if (!empty($conditions)) {
            foreach ($conditions as $condition) {
                if (is_string($condition)) {
                    $command .= $condition;
                } else {
                    $nestedCond = $condition[0];
                    unset($condition[0]);
                    $command .= sprintf($nestedCond, $this->buildCondition($condition, ''));
                }
            }

            return $command;
        }

        return null;
    }

    protected function buildLimit(): string
    {
        return "limit {$this->limit}";
    }

    protected function buildOffset(): string
    {
        return "offset {$this->offset}";
    }

    protected function buildGroupBy(): string
    {
        $groupColumns = implode(', ', $this->backtick($this->segments['groupBy']));
        return "group by $groupColumns";
    }

    protected function buildOrderBy(): string
    {
        $sql = 'order by ';
        $firstKey = array_key_first($this->segments['orderBy']);
        foreach ($this->segments['orderBy'] as $column => $order) {
            if ($column !== $firstKey) {
                $sql .= ", ";
            }

            if ($order instanceof QueryRaw) {
                $sql .= (string) $order;
                $this->addBindValues($order->getBindings(), 'orderBy');
                continue;
            }
            $sql .= "{$this->getColumn($column)} $order";
        }
        return $sql;
    }

    protected function buildUnion(): string
    {
        $query = null;
        if (!empty($unions = $this->segments['union'])) {
            foreach ($unions as $union) {
                $query .= "{$union[0]} {$union[1]}";
            }
        }
        return $query;
    }

    protected function buildHaving(): string
    {
        if (empty($this->segments['having'])) {
            return '';
        }

        $having = 'having ';
        foreach ($this->segments['having'] as $idx => $havingClause) {
            if ($idx === 0) {
                $having .= "{$havingClause[1]}";
            } else {
                $having .= "{$havingClause[0]}{$havingClause[1]}";
            }
        }
        return $having;
    }

    protected function buildLock(): string
    {
        return $this->segments['lock'];
    }

    protected function addBindValue(mixed $value, string $fieldName): void
    {
        if ($value instanceof Queryable) {
            $this->bindings[$fieldName] = array_merge($this->bindings[$fieldName], $value->getBindings());
            return;
        }
        $this->bindings[$fieldName][] = $value;
    }

    protected function addBindValues(iterable $fields, string $fieldName): void
    {
        foreach ($fields as $index => $value) {
            if (is_int($index) && is_iterable($value)) {
                $this->addBindValues($value, $fieldName);
                continue;
            }
            $this->addBindValue($value, $fieldName);
        }
    }

    public function getBindings(): array
    {
        $bindings = [];
        foreach ($this->bindings as $value) {
            if (!empty($value)) {
                array_push($bindings, ...$value);
            }
        }
        return $bindings;
    }

    public function lock(string $lock): static
    {
        $this->segments['lock'] = $lock;
        return $this;
    }

    public function lockForUpdate(): static
    {
        return $this->lock('for update');
    }

    public function sharedLock(): static
    {
        return $this->lock('lock in share mode');
    }

    protected function setTypeDML(string $type): void
    {
        $this->segments['insert'] = false;
        $this->segments['update'] = false;
        $this->segments['delete'] = false;
        $this->segments['select'] = false;
        $this->segments[$type] = true;
    }

    protected function backtick(array $items): array
    {
        return array_map([$this, 'getColumn'], $items);
    }

    protected function getTable(?string $table = null, bool $otherTable = false): string
    {
        $table = is_null($table) ? $this->table : $table;

        $prefix = $this->getPrefix();
        $alias = !empty($this->alias) && !$otherTable ? $this->alias : '';
        if (is_string($table)) {
            if (strpos($table, ' as ') !== false) {
                [$table, $alias] = explode(' as ', $table);
            }

            if (!empty($alias)) {
                return "`$prefix$table` as `$prefix$alias`";
            }

            return "`$prefix$table`";
        }

        return !empty($alias) ? "($table) as `$prefix$alias`" : "($table)";
    }

    protected function getPrefix(): ?string
    {
        return $this->connection->getPrefix();
    }

    protected function getColumn(string|QueryRaw $column): string
    {
        if ($column instanceof QueryRaw) {
            return $column->sql;
        }

        $alias = '';
        if (strpos($column, ' as ') !== false) {
            [$column, $alias] = explode(' as ', $column);
        }

        if (trim($column) === '*') {
            return '*';
        }

        if (str_contains($column, '.')) {
            $segments = explode('.', $column);
            $prefix = $this->getPrefix();

            if (trim($segments[1]) === '*') {
                $column = "`$prefix{$segments[0]}`.*";
            } else {
                $column = "`$prefix{$segments[0]}`.`{$segments[1]}`";
            }
        } else {
            $column = "`$column`";
        }

        if (!empty($alias)) {
            return "$column as `$alias`";
        }

        return $column;
    }

    public function toSql(): string
    {
        $segments = array_filter($this->segments, fn ($segment) => !empty($segment));
        foreach ($segments as $key => &$segment) {
            $key = ucfirst($key);
            $segment = call_user_func([$this, "build{$key}"]);
        }
        unset($segment);

        $query = implode(' ', $segments);
        $prefix = $this->getPrefix();
        if (!empty($segments['select']) && !empty($this->wrap)) {
            return "($query) as `$prefix{$this->wrap}`";
        }
        return $query;
    }

    public function toRawSql(bool $pretty = false, Highlighter $highlighter = new NullHighlighter()): string
    {
        $sql = $this->toSql();
        $bindings = $this->getBindings();
        foreach ($bindings as $value) {
            if (!is_resource($value)) {
                $position = strpos($sql, '?');
                if ($position !== false) {
                    if ($value instanceof UnitEnum) {
                        $value = $value->value;
                    }

                    if (is_bool($value)) {
                        $value = (int) $value;
                    }

                    if (!is_int($value) && !is_float($value)) {
                        $value = (string) $value;
                        $value = "'$value'";
                    }
                    $sql = substr_replace($sql, $value, $position, 1);
                }
            }
        }
        return $pretty ? (new SqlFormatter($highlighter))->format($sql) : $sql;
    }

    public function explain(): array
    {
        return $this->run('select', function (Connection $connection) {
            return $connection->select("explain {$this->toSql()}", $this->getBindings());
        });
    }

    public function __toString(): string
    {
        return $this->toSql();
    }

    protected function dynamicWhere(string $method, mixed $value, string $condition)
    {
        $string = substr($method, 5);
        $segments = preg_split(
            '/(And|Or)(?=[A-Z])/', $string, -1, PREG_SPLIT_DELIM_CAPTURE
        );

        $connector = 'and';
        foreach ($segments as $segment) {
            if ($segment === 'And' || $segment === 'Or') {
                $connector = strtolower($segment);
            } else {
                $this->where(Str::snake($segment), $value, $condition, $connector);
            }
        }

        return $this;
    }

    public function __call(string $method, array $parameters = []): mixed
    {
        if (str_starts_with($method, 'where')) {
            return $this->dynamicWhere($method, $parameters[0], !empty($parameters[1]) ? $parameters[1] : '=');
        }
        return $this->___call($method, $parameters);
    }
}
