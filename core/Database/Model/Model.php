<?php

namespace Ryra\Core\Database\Model;

use Ryra\Core\Database\Model\Traits\{
    HasRelationships,
    HidesAttributes,
    HasAttributes,
    HasScopes,
    HasEvents,
    SoftDeletes
};
use Ryra\Core\Collection\Arr;
use ReflectionAttribute;
use IteratorAggregate;
use Ryra\Core\App;
use JsonSerializable;
use ReflectionObject;
use ArrayIterator;
use ArrayAccess;
use Traversable;
use Ryra\Core\Database\Connection;
use Ryra\Core\Database\Model\Scopes\NotDeletedScope;
use Ryra\Core\Date\Date;
use Ryra\Core\Support\Arrayable;

/**
 * @method static \Ryra\Core\Database\Model\Model setModel(\Ryra\Core\Database\Model\Model $model)
 * @method static \Ryra\Core\Database\Model\Collection get(array $columns = [])
 * @method static \Ryra\Core\Database\Model\Model find(mixed $id, array|string|null $columns = null)
 * @method static \Ryra\Core\Database\Model\Model findOrFail(mixed $id, array|string|null $columns = null)
 * @method static \Ryra\Core\Database\Model\Model whereKey(mixed $id)
 * @method static \Ryra\Core\Database\Model\Model withoutGlobalScope(string $globalScope)
 * @method static \Ryra\Core\Database\Model\Model withoutGlobalScopes(array $globalScopes)
 * @method static \Ryra\Core\Database\Model\Model|false create(iterable $fields)
 * @method static \Ryra\Core\Database\Model\Model|false firstOrCreate(iterable $where, iterable $fields = [])
 * @method static \Ryra\Core\Database\Model\Model firstOrNew(iterable $where, iterable $fields = [])
 * @method static \Ryra\Core\Database\Model\Model|false updateOrCreate(iterable $where, iterable $fields = [])
 * @method static string toSql()
 * @method static string toRawSql()
 * @method static \Ryra\Core\Pagination\Paginator paginate(int $perPage, ?int $page = null, ?string $pageName = null, ?int $total = null)
 * @method static \Ryra\Core\Pagination\Paginator fastPaginate(int $perPage, ?int $page = null, ?string $pageName = null, ?int $total = null)
 * @method static \Ryra\Core\Database\Model\Model when(bool $condition, callable $callback, ?callable $default = null)
 * @method static \Ryra\Core\Database\Model\Model unless(bool $condition, callable $callback, ?callable $default = null)
 * @method static \Ryra\Core\Database\Model\Model from(\Ryra\Core\Support\Queryable|string $table, ?string $alias = null)
 * @method static \Ryra\Core\Database\Model\Model table(\Ryra\Core\Support\Queryable|string $table, ?string $alias = null)
 * @method static \Ryra\Core\Database\Model\Model alias(string $alias)
 * @method static \Ryra\Core\Database\Model\Model wrap(string $alias)
 * @method static \Ryra\Core\Database\Model\Model select(\Ryra\Core\Support\Queryable|string ...$columns)
 * @method static \Ryra\Core\Database\Model\Model addSelect(\Ryra\Core\Support\Queryable|string ...$columns)
 * @method static \Ryra\Core\Database\Model\Model selectRaw(string $query, array $bindings = [])
 * @method static \Ryra\Core\Database\Model\Model addSelectRaw(string $query, array $bindings = [])
 * @method static int insert(iterable $fields)
 * @method static int insertOrIgnore(iterable $fields)
 * @method static int insertGetId(iterable $fields, string $primaryKey = 'id')
 * @method static int insertUsing(array $columns, \Closure|\Ryra\Core\Support\Queryable $query)
 * @method static int delete()
 * @method static int update(iterable $fields)
 * @method static array toArray()
 * @method static \Ryra\Core\Database\Model\Model limit(int $limit, ?int $offset = null)
 * @method static mixed value(string $column)
 * @method static \Ryra\Core\Collection\Collection pluck(string $firstColumn, ?string $secondColumn = null)
 * @method static \Ryra\Core\Database\Model\Model offset(int $offset)
 * @method static \Ryra\Core\Database\Model\Model groupBy(string ...$groupColumn)
 * @method static \Ryra\Core\Database\Model\Model orderBy(\Ryra\Core\Database\Query\QueryRaw|string $column, string $order = 'asc')
 * @method static \Ryra\Core\Database\Model\Model orderByDesc(string $column)
 * @method static \Ryra\Core\Database\Model\Model orderByRaw(string $orderBy, array $bindings = [])
 * @method static \Ryra\Core\Database\Model\Model reorder(string $column, string $order = 'asc')
 * @method static \Ryra\Core\Database\Model\Model oldest(string $column = 'created_at')
 * @method static \Ryra\Core\Database\Model\Model latest(string $column = 'created_at')
 * @method static int count(?string $column = null)
 * @method static int max(string $column)
 * @method static int min(string $column)
 * @method static int avg(string $column)
 * @method static float sum(string $column)
 * @method static mixed first()
 * @method static \Ryra\Core\Database\Model\Model firstOrFail()
 * @method static bool exists()
 * @method static bool doesntExist()
 * @method static \Generator cursor()
 * @method static \Ryra\Core\Database\Model\Model where(string|array|callable $columns, mixed $value = null, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Model orWhere(mixed $columns, mixed $value = null, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Model whereNull(string $column)
 * @method static \Ryra\Core\Database\Model\Model whereNot(mixed $columns, mixed $value = null, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Model orWhereNot(mixed $columns, mixed $value = null, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Model whereNotNull(string $column)
 * @method static \Ryra\Core\Database\Model\Model orWhereNull(string $column)
 * @method static \Ryra\Core\Database\Model\Model orWhereNotNull(string $column)
 * @method static \Ryra\Core\Database\Model\Model whereAny(array $columns, mixed $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Model orWhereAny(array $columns, mixed $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Model whereAll(array $columns, mixed $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Model orWhereAll(array $columns, mixed $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Model whereContains(string $column, mixed $value)
 * @method static \Ryra\Core\Database\Model\Model orWhereContains(string $column, mixed $value)
 * @method static \Ryra\Core\Database\Model\Model whereDate(string $column, string|\DateTimeInterface $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Model orWhereDate(string $column, string|DateTimeInterface $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Model whereMonth(string $column, string|\DateTimeInterface $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Model orWhereMonth(string $column, string|\DateTimeInterface $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Model whereDay(string $column, string|\DateTimeInterface $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Model orWhereDay(string $column, string|\DateTimeInterface $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Model whereYear(string $column, string|\DateTimeInterface $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Model orWhereYear(string $column, string|DateTimeInterface $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Model whereTime(string $column, string|\DateTimeInterface $value, string $condition = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Model orWhereTime(string $column, string|\DateTimeInterface $value, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Model whereIn(string $column, iterable|callable|\Ryra\Core\Support\Queryable $values, bool $isNot = false, string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Model orWhereIn(string $column, iterable|callable|\Ryra\Core\Support\Queryable $values)
 * @method static \Ryra\Core\Database\Model\Model whereNotIn(string $column, iterable|callable|\Ryra\Core\Support\Queryable $values)
 * @method static \Ryra\Core\Database\Model\Model orWhereNotIn(string $column, iterable|callable|\Ryra\Core\Support\Queryable $values)
 * @method static \Ryra\Core\Database\Model\Model whereBetween(string $column, array $values, string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Model orWhereBetween(string $column, array $values)
 * @method static \Ryra\Core\Database\Model\Model whereColumn(string|array $first, string|array|null $second = null, string $operator = '=', string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Model orWhereColumn(string|array $first, string|array|null $second = null, string $operator = '=')
 * @method static \Ryra\Core\Database\Model\Model whereExists(callable|\Ryra\Core\Support\Queryable $callback, string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Model orWhereExists(callable|\Ryra\Core\Support\Queryable $callback)
 * @method static \Ryra\Core\Database\Model\Model whereNotExists(callable|\Ryra\Core\Support\Queryable $callback, string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Model orWhereNotExists(callable|\Ryra\Core\Support\Queryable $callback)
 * @method static \Ryra\Core\Database\Model\Model whereRaw(string $query, array $bindings = [], string $logicalOperator = 'and')
 * @method static \Ryra\Core\Database\Model\Model orWhereRaw(string $query, array $bindings = [])
 * @method static \Ryra\Core\Database\Model\Model join(string $table, string|callable $first, ?string $second = null, string $condition = '=', string $type = 'inner')
 * @method static \Ryra\Core\Database\Model\Model leftJoin(string $table, string|callable $first, ?string $second = null, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Model rightJoin(string $table, string|callable $first, ?string $second = null, string $condition = '=')
 * @method static \Ryra\Core\Database\Model\Model joinSub(\Ryra\Core\Support\Queryable $table, string $alias, callable $callback, string $type = 'inner')
 * @method static \Ryra\Core\Database\Model\Model leftJoinSub(\Ryra\Core\Support\Queryable $table, string $alias, callable $callback)
 * @method static \Ryra\Core\Database\Model\Model rightJoinSub(\Ryra\Core\Support\Queryable $table, string $alias, callable $callback)
 */
class Model implements IteratorAggregate, JsonSerializable, ArrayAccess, Arrayable
{
    use HasRelationships;
    use HidesAttributes;
    use HasAttributes;
    use HasScopes;
    use HasEvents;

    /**
     * The name of the "created at" column.
     */
    public const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     */
    public const UPDATED_AT = 'updated_at';

    public bool $timestamps = true;

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    protected bool $incrementing = true;

    /**
     * The connection name for the model.
     */
    protected ?string $connection = null;

    /**
     * The table associated with the model.
     */
    protected string $table;

    /**
     * The primary key for the table
     */
    protected ?string $primaryKey = null;

    protected static array $booted = [];

    public function __construct()
    {
        $this->bootIfNotBooted();
    }

    protected function bootIfNotBooted()
    {
        if (!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;
            if ($this->checkUseClass(HasAttributes::class)) {
                $this->registerAccessors();
                $this->registerMutators();
            }

            if ($this->checkUseClass(HasScopes::class)) {
                $this->addLocalScopes();
                $this->addGlobalScopes();

                if ($this->checkUseClass(SoftDeletes::class)) {
                    $this->addGlobalScope(NotDeletedScope::class);
                }
            }
            
            if ($this->checkUseClass(HasRelationships::class)) {
                $this->registerRelationships();
            }

            if ($this->timestamps) {
                static::creating(function (Model $model) {
                    $model->{static::CREATED_AT} = Date::now();
                });

                static::updating(function (Model $model) {
                    $model->{static::UPDATED_AT} = Date::now();
                });
            }

            $this->registerEvents();
            static::booting();
            static::boot();
            static::booted();
        }
    }

    protected static function booting(): void {}

    protected static function boot(): void {}

    protected static function booted(): void {}

    protected function checkUseClass(string $class): bool
    {
        return in_array($class, $this->classUsesRecursive($this));
    }

    protected function classUsesRecursive(string|object $class): array
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];

        do {
            $results += class_uses($class);
        } while ($class = get_parent_class($class));

        foreach ($results as $trait) {
            $results += $this->classUsesRecursive($trait);
        }

        return array_unique($results);
    }

    public function incrementing(): bool
    {
        return $this->incrementing;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function setTable(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    public function getKeyName(): string
    {
        return $this->primaryKey ?? 'id';
    }

    public function getKey(): mixed
    {
        return $this->getOriginal($this->getKeyName());
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->toArray());
    }

    public function jsonSerialize(): ?array
    {
        $attributes = $this->toArray();
        return Arr::except(array_diff($attributes, $this->visible), $this->hidden);
    }

    public static function query(): Builder
    {
        return (new static)->newModelQuery();
    }

    public function connection(): Connection
    {
        return App::service('database')->getConnection($this->connection);
    }

    public function newModelQuery(): Builder
    {
        $builder = $this->connection()->query();
        $builder = new Builder($builder);
        return $builder->from($this->table)->setModel($this);
    }

    public function save(): bool
    {
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        $query = $this->newModelQuery();

        if (!empty($this->getKey())) {
            $saved = $this->performUpdate($query);
        } else {
            $saved = $this->performInsert($query);
        }

        if ($saved) {
            $this->fireModelEvent('saved');
        }

        return $saved;
    }

    public function update(iterable $fields): bool
    {
        return $this->setAttributes($fields)->save();
    }

    public function delete(): bool|null
    {
        if (is_null($this->getKeyName())) {
            throw new \LogicException('No primary key defined on model.');
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        $query = $this->newModelQuery();
        $deleted = (int) $query->whereKey($this->getKey())->delete();

        if ($deleted) {
            $this->fireModelEvent('deleted');
        }
        return $deleted;
    }

    public function isDirty(): bool
    {
        foreach ($this->getAttributes() as $field => $value) {
            if (!isset($this->original[$field])) {
                return true;
            }

            if ($value != $this->original[$field]) {
                return true;
            }
        }
        return false;
    }

    public function newModel(iterable $fields): static
    {
        return (new static())->setAttributes($fields);
    }

    protected function performUpdate(Builder $query): bool
    {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        $updated = (int) $query
            ->whereKey($this->getKey())
            ->update($this->getAttributes());
        if ($updated) {
            $this->fireModelEvent('updated');
        }

        return $updated;
    }

    protected function performInsert(Builder $query): bool
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        $attributes = $this->getAttributes();
        $missingKey = empty($attributes[$this->getKeyName()]);
        if ($this->incrementing() && $missingKey) {
            $inserted = $this->insertAndSetId($query);
        } else {
            if (method_exists($this, 'newUniqueId') && $missingKey) {
                $attributes[$this->getKeyName()] = $this->newUniqueId();
            }
            $inserted = (bool) $query->insert($attributes);
            $this->original = $attributes;
            $this->attributes = $attributes;
            $this->exists = true;
        }

        if ($inserted) {
            $this->fireModelEvent('created');
        }
        return $inserted;
    }

    protected function insertAndSetId(Builder $query): bool
    {
        $key = $query->insertGetId($this->getAttributes());
        if ($key > 0) {
            $this->attributes[$this->getKeyName()] = $key;
            $this->original = $this->attributes;
            $this->exists = true;
            return true;
        }
        return false;
    }

    protected function getReflectionObject(): ReflectionObject
    {
        return new ReflectionObject($this);
    }

    protected function getObjectAttributes(?string $attribute = null): array
    {
        $attributes = $this->getReflectionObject()->getAttributes($attribute);
        return array_map(fn (ReflectionAttribute $attribute) => $attribute->newInstance(), $attributes);
    }

    protected function getReflectionMethodsByAttribute(string $attribute): array
    {
        $methods = $this->getReflectionObject()->getMethods();
        return array_filter($methods, function ($method) use ($attribute) {
            return !empty($method->getAttributes($attribute));
        });
    }

    protected function getReflectionMethodsByReturnType(string $returnType): array
    {
        $methods = $this->getReflectionObject()->getMethods();
        return array_filter($methods, function ($method) use ($returnType) {
            $type = (string) $method->getReturnType();
            return $type === $returnType || (class_exists($type) && is_subclass_of($type, $returnType));
        });
    }

    protected function getClassAttributes(?string $attribute = null): ?array
    {
        return $this->getReflectionObject()->getAttributes($attribute);
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->newModelQuery()->{$method}(...$parameters);
    }

    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new static())->newModelQuery()->{$method}(...$parameters);
    }
}