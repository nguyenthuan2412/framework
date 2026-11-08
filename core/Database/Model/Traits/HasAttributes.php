<?php

namespace Ryra\Core\Database\Model\Traits;

use Ryra\Core\App;
use Ryra\Core\Database\Attributes\Accessor;
use Ryra\Core\Database\Attributes\Mutator;
use Ryra\Core\Database\Model\CastsAttributes;

trait HasAttributes
{
    protected array $appends = [];

    protected array $original = [];

    protected array $attributes = [];

    protected array $casts = [];

    protected static array $accessors = [];

    protected static array $mutators = [];

    protected bool $exists = false;

    protected function registerAccessors(): void
    {
        $attribute = Accessor::class;
        $methods = $this->getReflectionMethodsByAttribute($attribute);
        foreach ($methods as $method) {
            $reflectionAttribute = $method->getAttributes($attribute)[0];
            $accessor = (string)$reflectionAttribute->newInstance();
            static::$accessors[static::class][$accessor] = $method->getName();
        }
    }

    protected function registerMutators(): void
    {
        $attribute = Mutator::class;
        $methods = $this->getReflectionMethodsByAttribute($attribute);
        foreach ($methods as $method) {
            $reflectionAttribute = $method->getAttributes($attribute)[0];
            $mutator = (string)$reflectionAttribute->newInstance();
            static::$mutators[static::class][$mutator] = $method->getName();
        }
    }

    public function append(string|array $fields): static
    {
        $this->appends = array_values(array_unique(
            array_merge($this->appends, is_string($fields) ? func_get_args() : $fields)
        ));
        return $this;
    }

    public function setAppends(string|array $appends): static
    {
        $this->appends = is_string($appends) ? func_get_args() : $appends;
        return $this;
    }

    public function getAppends(): array
    {
        return $this->appends;
    }

    public function hasMutator(?string $field = null): bool
    {
        return is_null($field)
            ? !empty(static::$mutators[static::class])
            : isset(static::$mutators[static::class][$field]);
    }

    public function hasAccessor(?string $field = null): bool
    {
        return is_null($field)
            ? !empty(static::$accessors[static::class])
            : isset(static::$accessors[static::class][$field]);
    }

    public function getKey(): mixed
    {
        return $this->original[$this->primaryKey];
    }

    public function setAttributes(iterable $fields): static
    {
        foreach ($fields as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        if ($this->hasMutator($key)) {
            $method = static::$mutators[static::class][$key];
            $value = $this->{$method}($value);
        }

        $this->attributes[$key] = $value;

        if (
            isset($this->casts[$key])
            && is_subclass_of($this->casts[$key], CastsAttributes::class)
        ) {
            $cast = $this->casts[$key];
            $cast = App::make($cast);
            $this->attributes[$key] = $cast->set($this, $key, $value, $this->attributes);
        }

        return $this;
    }

    public function setData(array $data): static
    {
        $this->original = $data;
        $this->attributes = $data;
        $this->exists = true;
        return $this;
    }

    public function getOriginal(?string $field = null): mixed
    {
        if (is_null($field)) {
            return $this->original;
        }
        return $this->original[$field] ?? null;
    }

    public function getAttribute(string $key): mixed
    {
        $value = $this->attributes[$key] ?? null;
        if ($this->hasAccessor($key)) {
            $method = static::$accessors[static::class][$key];
            return $this->{$method}($value);
        }

        if (
            isset($this->casts[$key])
            && is_subclass_of($this->casts[$key], CastsAttributes::class)
        ) {
            $cast = $this->casts[$key];
            $cast = App::make($cast);
            $value = $cast->get($this, $key, $value, $this->attributes);
        }

        return $value;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    protected function getFieldNames(): array
    {
        return array_values(array_unique(array_merge(
            array_keys($this->attributes),
            $this->appends
        )));
    }

    public function toArray(): array
    {
        $fieldNames = $this->getFieldNames();
        $fields = [];
        foreach ($fieldNames as $fieldName) {
            $fields[$fieldName] = $this->getAttribute($fieldName);
        }
        return $fields;
    }

    public function hasAttribute(string $name): bool
    {
        $issetAttribute = isset($this->attributes[$name]);
        return $issetAttribute || in_array($name, $this->appends);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->hasAttribute($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    public function withCasts(array $casts): static
    {
        $this->casts = array_merge($this->casts, $casts);
        return $this;
    }

    public function __get(string $name): mixed
    {
        if ($this->hasAttribute($name)) {
            return $this->getAttribute($name);
        }

        if (!empty($this->relations[$name])) {
            return $this->relations[$name]()->getResults();
        }

        return null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->setAttribute($name, $value);
    }

    public function __isset(string $name): bool
    {
        return $this->hasAttribute($name);
    }
}