<?php

namespace Ryra\Core\Database\Model\Traits;

use Ryra\Core\Database\Attributes\GlobalScope;
use Ryra\Core\Database\Attributes\Scope;
use Ryra\Core\Database\Model\Builder;

trait HasScopes
{
    protected static array $localScopes = [];

    protected static array $globalScopes = [];

    protected static array $callableGlobalScopes = [];

    protected function addLocalScopes(): void
    {
        $attribute = Scope::class;
        $methods = $this->getReflectionMethodsByAttribute($attribute);
        foreach ($methods as $method) {
            static::$localScopes[static::class][] = $method->getName();
        }
    }

    protected function addGlobalScopes(): void
    {
        $attributes = $this->getObjectAttributes(GlobalScope::class);
        foreach ($attributes as $attribute) {
            foreach ($attribute->scopes as $scope) {
                if (!$this->hasGlobalScope($scope)) {
                    $this->addGlobalScope($scope);
                }
            }
        }

        $attribute = GlobalScope::class;
        $methods = $this->getReflectionMethodsByAttribute($attribute);
        foreach ($methods as $method) {
            $attribute = $method->getAttributes(GlobalScope::class)[0]->newInstance();
            $scope = $attribute->scopes[0];
            if (!$this->hasGlobalScope($scope)) {
                $this->addGlobalScope($scope);
                static::$callableGlobalScopes[static::class][$scope] = [static::class, $method->getName()];
            }
        }
    }

    public function hasCallableGlobalScope(string $scope): bool
    {
        return isset(static::$callableGlobalScopes[static::class][$scope]);
    }

    public function getCallableGlobalScope(string $scope): callable
    {
        return static::$callableGlobalScopes[static::class][$scope];
    }

    protected function addGlobalScope(string $scope): static
    {
        static::$globalScopes[static::class][] = $scope;
        return $this;
    }

    public static function hasLocalScope(string $scope): bool
    {
        return in_array($scope, static::$localScopes[static::class] ?? []);
    }

    public function callScope(string $method, Builder $builder, mixed ...$params): void
    {
        $this->{$method}($builder, ...$params);
    }

    public static function getLocalScopes(): array
    {
        return static::$localScopes[static::class] ?? [];
    }

    public static function hasGlobalScope(string $scope): bool
    {
        return in_array($scope, static::$globalScopes[static::class] ?? []);
    }

    public static function getGlobalScopes()
    {
        return static::$globalScopes[static::class] ?? [];
    }
}