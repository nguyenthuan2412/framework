<?php

namespace Ryra\Core\Database\Model;

use Ryra\Core\Database\Exception\ModelNotFoundException;
use Ryra\Core\Database\Query\Builder as QueryBuilder;
use Ryra\Core\Database\Query\Traits\BuildsQueries;
use Ryra\Core\Database\Scope;
use Ryra\Core\App;
use Ryra\Core\Collection\Arr;
use Ryra\Core\Pagination\Paginator;
use Ryra\Core\Pagination\CursorPaginator;
use Ryra\Core\Support\Queryable;

/**
 * @method static \Ryra\Core\Database\Model\Builder setModel(\Ryra\Core\Database\Model\Model $model)
 * @method static \Ryra\Core\Database\Model\Collection get(array $columns = [])
 * @method static \Ryra\Core\Database\Model\Model find(mixed $id, array|string|null $columns = null)
 * @method static \Ryra\Core\Database\Model\Model findOrFail(mixed $id, array|string|null $columns = null)
 * @method static \Ryra\Core\Database\Model\Builder whereKey(mixed $id)
 * @method static \Ryra\Core\Database\Model\Builder withoutGlobalScope(string $globalScope)
 * @method static \Ryra\Core\Database\Model\Builder withoutGlobalScopes(array $globalScopes)
 * @method static \Ryra\Core\Database\Model\Model|false create(iterable $fields)
 * @method static string toSql()
 * @method static string toRawSql()
 * @method static \Ryra\Core\Pagination\Paginator paginate(int $perPage, ?int $page = null, ?string $pageName = null, ?int $total = null)
 * @method static \Ryra\Core\Pagination\Paginator fastPaginate(int $perPage, ?int $page = null, ?string $pageName = null, ?int $total = null)
 * @method static \Ryra\Core\Pagination\CursorPaginator cursorPaginate(int $perPage, ?string $cursorName = null, ?string $cursor = null)
 * @method static \Ryra\Core\Database\Model\Builder from(\Ryra\Core\Support\Queryable|string $table, ?string $alias = null)
 * @method static \Ryra\Core\Database\Model\Builder table(\Ryra\Core\Support\Queryable|string $table, ?string $alias = null)
 * @method static \Ryra\Core\Database\Model\Builder wrap(string $alias)
 * @method static \Ryra\Core\Database\Model\Builder select(\Ryra\Core\Support\Queryable|string ...$columns)
 * @method static \Ryra\Core\Database\Model\Builder addSelect(\Ryra\Core\Support\Queryable|string ...$columns)
 * @method static \Ryra\Core\Database\Model\Builder selectRaw(string $query, array $bindings = [])
 * @method static \Ryra\Core\Database\Model\Builder addSelectRaw(string $query, array $bindings = [])
 * @method static int insert(iterable $fields)
 * @method static int insertOrIgnore(iterable $fields)
 * @method static int insertGetId(iterable $fields, string $primaryKey = 'id')
 * @method static int insertUsing(array $columns, \Closure|\Ryra\Core\Support\Queryable $query)
 * @method static int delete()
 * @method static int update(iterable $fields)
 * @method static array toArray()
 * @method static \Ryra\Core\Database\Model\Builder limit(int $limit, ?int $offset = null)
 * @method static mixed value(string $column)
 * @method static \Ryra\Core\Collection\Collection pluck(string $firstColumn, ?string $secondColumn = null)
 * @method static \Ryra\Core\Database\Model\Builder offset(int $offset)
 * @method static \Ryra\Core\Database\Model\Builder groupBy(string ...$groupColumn)
 * @method static \Ryra\Core\Database\Model\Builder orderBy(\Ryra\Core\Database\Query\QueryRaw|string $column, string $order = 'asc')
 * @method static \Ryra\Core\Database\Model\Builder orderByDesc(string $column)
 * @method static \Ryra\Core\Database\Model\Builder orderByRaw(string $orderBy, array $bindings = [])
 * @method static \Ryra\Core\Database\Model\Builder reorder(string $column, string $order = 'asc')
 * @method static \Ryra\Core\Database\Model\Builder oldest(string $column = 'created_at')
 * @method static \Ryra\Core\Database\Model\Builder latest(string $column = 'created_at')
 * @method static int count(?string $column = null)
 * @method static int max(string $column)
 * @method static int min(string $column)
 * @method static int avg(string $column)
 * @method static float sum(string $column)
 * @method static mixed first()
 * @method static bool exists()
 * @method static bool doesntExist()
 * @method static \Generator cursor()
 * @method static \Ryra\Core\Database\Model\Builder where(string|array|callable $columns, mixed $value = null, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Builder orWhere(mixed $columns, mixed $value = null, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Builder whereNull(string $column)
 * @method static \Ryra\Core\Database\Model\Builder whereNot(mixed $columns, mixed $value = null, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Builder orWhereNot(mixed $columns, mixed $value = null, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Builder whereNotNull(string $column)
 * @method static \Ryra\Core\Database\Model\Builder orWhereNull(string $column)
 * @method static \Ryra\Core\Database\Model\Builder orWhereNotNull(string $column)
 * @method static \Ryra\Core\Database\Model\Builder whereAny(array $columns, mixed $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Builder orWhereAny(array $columns, mixed $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Builder whereAll(array $columns, mixed $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Builder orWhereAll(array $columns, mixed $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Builder whereContains(string $column, mixed $value)
 * @method static \Ryra\Core\Database\Model\Builder orWhereContains(string $column, mixed $value)
 * @method static \Ryra\Core\Database\Model\Builder whereDate(string $column, string|\DateTimeInterface $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Builder orWhereDate(string $column, string|DateTimeInterface $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Builder whereMonth(string $column, string|\DateTimeInterface $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Builder orWhereMonth(string $column, string|\DateTimeInterface $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Builder whereDay(string $column, string|\DateTimeInterface $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Builder orWhereDay(string $column, string|\DateTimeInterface $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Builder whereYear(string $column, string|\DateTimeInterface $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Builder orWhereYear(string $column, string|DateTimeInterface $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Builder whereTime(string $column, string|\DateTimeInterface $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Builder orWhereTime(string $column, string|\DateTimeInterface $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Builder whereIn(string $column, iterable|callable|\Ryra\Core\Support\Queryable $values, bool $isNot = false, string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Builder orWhereIn(string $column, iterable|callable|\Ryra\Core\Support\Queryable $values)
 * @method static \Ryra\Core\Database\Model\Builder whereNotIn(string $column, iterable|callable|\Ryra\Core\Support\Queryable $values)
 * @method static \Ryra\Core\Database\Model\Builder orWhereNotIn(string $column, iterable|callable|\Ryra\Core\Support\Queryable $values)
 * @method static \Ryra\Core\Database\Model\Builder whereBetween(string $column, array $values, string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Builder orWhereBetween(string $column, array $values)
 * @method static \Ryra\Core\Database\Model\Builder whereColumn(string|array $first, string|array|null $second = null, string $operator = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Builder orWhereColumn(string|array $first, string|array|null $second = null, string $operator = '=')
 * @method static \Ryra\Core\Database\Model\Builder whereExists(callable|\Ryra\Core\Support\Queryable $callback, string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Builder orWhereExists(callable|\Ryra\Core\Support\Queryable $callback)
 * @method static \Ryra\Core\Database\Model\Builder join(string $table, string|callable $first, ?string $second = null, string $condition = '=', string $type = 'inner')
 * @method static \Ryra\Core\Database\Model\Builder leftJoin(string $table, string|callable $first, ?string $second = null, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Builder rightJoin(string $table, string|callable $first, ?string $second = null, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Builder joinSub(\Ryra\Core\Support\Queryable $table, string $alias, callable $callback, string $type = 'inner')
 * @method static \Ryra\Core\Database\Model\Builder leftJoinSub(\Ryra\Core\Support\Queryable $table, string $alias, callable $callback)
 * @method static \Ryra\Core\Database\Model\Builder rightJoinSub(\Ryra\Core\Support\Queryable $table, string $alias, callable $callback)
 */
class Builder implements Queryable
{
    use BuildsQueries;

    protected ?Model $model = null;

    protected array $withoutGlobalScopes = [];

    public function __construct(protected QueryBuilder $query) {}

    public function setModel(Model $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function get(array $columns = []): Collection
    {
        return $this->applyGlobalScopes()
            ->get($columns, get_class($this->model), Collection::class);
    }

    public function cursor(array $columns = []): LazyCollection
    {
        return $this->applyGlobalScopes()
            ->cursor($columns, get_class($this->model), LazyCollection::class);
    }

    public function first(): mixed
    {
        return $this->limit(1)->get()->first();
    }

    public function pluck(string $firstColumn, ?string $secondColumn = null): Collection
    {
        return $this->get()->pluck($firstColumn, $secondColumn);
    }

    public function find(mixed $id, array|string|null $columns = null): ?Model
    {
        return $this->whereKey($id)->get(Arr::wrap($columns))->first();
    }

    public function findOrFail(mixed $id, array|string|null $columns = null): Model
    {
        return $this->whereKey($id)->select(...Arr::wrap($columns))->firstOrFail();
    }

    public function firstOrFail(): Model
    {
        $result = $this->first();
        if (empty($result)) {
            throw new ModelNotFoundException;
        }
        return $result;
    }

    public function whereKey(mixed $id): static
    {
        $this->query->where($this->model->getKeyName(), $id);
        return $this;
    }

    public function withoutGlobalScope(string $globalScope): static
    {
        if (!in_array($globalScope, $this->withoutGlobalScopes)) {
            $this->withoutGlobalScopes[] = $globalScope;
        }
        return $this;
    }

    public function withoutGlobalScopes(array $globalScopes = []): static
    {
        if (empty($globalScopes)) {
            foreach ($this->model->getGlobalScopes() as $globalScope) {
                $this->withoutGlobalScope($globalScope);
            }
        } else {
            foreach ($globalScopes as $globalScope) {
                $this->withoutGlobalScope($globalScope);
            }
        }

        return $this;
    }

    protected function applyGlobalScopes(): QueryBuilder
    {
        $scopes = array_diff($this->model->getGlobalScopes(), $this->withoutGlobalScopes);
        foreach ($scopes as $scope) {
            if (is_a($scope, Scope::class, true)) {
                App::call([$scope, 'apply'], [$this, $this->model]);
                continue;
            }

            if ($this->model->hasCallableGlobalScope($scope)) {
                $scope = $this->model->getCallableGlobalScope($scope);
                $classModel = $scope[0];
                $methodModel = $scope[1];
                $classModel::{$methodModel}($this, $this->model);
            }
        }
        return $this->query;
    }

    public function create(iterable $fields): Model|false
    {
        $model = $this->model->newModel(iterator_to_list($fields));
        $saved = $model->save();
        if ($saved) {
            return $model;
        }
        return false;
    }

    public function firstOrCreate(iterable $where, iterable $fields = []): false|Model
    {
        $record = $this->firstOrNew($where, $fields);
        if (!is_null($record)) {
            return $record->save() ? $record : false;
        }
        return $record;
    }

    public function firstOrNew(iterable $where, iterable $fields = []): Model
    {
        $record = $this->where($where)->first();
        if (!empty($record)) {
            return $record;
        }
        $modelFields = [];
        foreach ($where as $key => $value) {
            $modelFields[$key] = $value;
        }
        foreach ($fields as $key => $value) {
            $modelFields[$key] = $value;
        }
        return $this->model->newModel($modelFields);
    }

    public function updateOrCreate(iterable $where, iterable $fields = []): false|Model
    {
        $record = $this->firstOrNew($where, $fields);
        foreach ($fields as $key => $value) {
            $record->{$key} = $value;
        }
        if ($record->save()) {
            return $record;
        }
        return false;
    }

    protected function fetchToModel(array $fields): Model
    {
        $model = get_class($this->model);
        return (new $model())->setData($fields);
    }

    public function toSql(): string
    {
        return $this->applyGlobalScopes()->toSql();
    }

    public function toRawSql(): string
    {
        return $this->applyGlobalScopes()->toRawSql();
    }

    public function getBindings(): array
    {
        return $this->query->getBindings();
    }

    public function paginate(
        int $perPage,
        ?int $page = null,
        ?string $pageName = null,
        ?int $total = null
    ): Paginator {
        return $this->applyGlobalScopes()->paginate(
            $perPage,
            $page,
            $pageName,
            $total,
            get_class($this->model),
            Collection::class
        );
    }

    public function fastPaginate(
        int $perPage,
        ?int $page = null,
        ?string $pageName = null,
        ?int $total = null
    ): Paginator {
        return $this->applyGlobalScopes()->fastPaginate(
            $perPage,
            $page,
            $this->model->getKeyName(),
            $pageName,
            $total,
            get_class($this->model),
            Collection::class
        );
    }

    public function cursorPaginate(
        int $perPage,
        ?string $cursorName = null,
        ?string $cursor = null
    ): CursorPaginator {
        return $this->applyGlobalScopes()->cursorPaginate(
            $perPage,
            $cursorName,
            $cursor,
            $this->model->getKeyName(),
            get_class($this->model),
            Collection::class
        );
    }

    public function __call(string $method, array $arguments): mixed
    {
        if ($this->model?->hasLocalScope($method)) {
            $this->model->callScope($method, $this, ...$arguments);
            return $this;
        }

        $result = $this->query->{$method}(...$arguments);
        if ($result === $this->query) {
            return $this;
        }
        return $result;
    }

    public function __toString(): string
    {
        return $this->toSql();
    }
}