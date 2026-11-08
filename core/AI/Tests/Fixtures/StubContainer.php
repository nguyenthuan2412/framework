<?php

namespace Ryra\Core\AI\Tests\Fixtures;

use BadMethodCallException;
use Closure;
use Ryra\Core\Contracts\Container\Container;
use RuntimeException;

/**
 * Container tối giản cho test: chỉ cần get()/has() để manager lấy được config.
 */
class StubContainer implements Container
{
    /**
     * @param array<string, mixed> $services
     */
    public function __construct(public array $services = [])
    {
    }

    public static function getInstance(): static
    {
        throw new BadMethodCallException('Not available in tests.');
    }

    public function get(string $id): mixed
    {
        return $this->services[$id] ?? throw new RuntimeException("Service \"{$id}\" is not registered.");
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    public function singleton(string $id, callable $callback): void
    {
    }

    public function singletonIf(string $id, callable $callback): void
    {
    }

    public function transient(string $id, callable $callback): void
    {
    }

    public function transientIf(string $id, callable $callback): void
    {
    }

    public function scoped(string $id, callable $callback): void
    {
    }

    public function scopedIf(string $id, callable $callback): void
    {
    }

    public function alias(string $id, string $alias): self
    {
        return $this;
    }

    public function getAlias(string $id): array
    {
        return [];
    }

    public function forgetScopedInstances(): void
    {
    }

    public static function make(string $concrete, array $parameters = []): object
    {
        return new $concrete(...$parameters);
    }

    public static function call(Closure|array $callback, array $parameters = []): mixed
    {
        return $callback(...$parameters);
    }

    public function __wakeup()
    {
    }
}
