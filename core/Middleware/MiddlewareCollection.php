<?php

namespace Ryra\Core\Middleware;

use Ryra\Core\Module\ModuleManager;

class MiddlewareCollection
{
    protected array $globalMiddlewares = [];

    protected array $moduleMiddlewares = [];

    public function __construct(protected ModuleManager $modules) {}

    public function add(string $middleware, ?string $module = null): void
    {
        if (is_null($module) && !in_array($middleware, $this->globalMiddlewares)) {
            $this->globalMiddlewares[] = $middleware;
            return;
        }

        if ($this->modules->has($module)) {
            if (!isset($this->moduleMiddlewares[$module])) {
                $this->moduleMiddlewares[$module] = [];
            }

            if (!in_array($middleware, $this->moduleMiddlewares[$module])) {
                $this->moduleMiddlewares[$module][] = $middleware;
            }
            return;
        }
    }

    public function use(array $middlewares, ?string $module = null): void
    {
        foreach ($middlewares as $middleware) {
            $this->add($middleware, $module);
        }
    }

    public function all(?string $module = null): array
    {
        if (is_null($module)) {
            return $this->globalMiddlewares;
        }

        return $this->moduleMiddlewares[$module] ?? [];
    }

    public function has(string $middleware, ?string $module = null): bool
    {
        if (is_null($module)) {
            return isset($this->globalMiddlewares[$middleware]);
        }

        return isset($this->moduleMiddlewares[$module][$middleware]);
    }

    public function getMiddleware(?string $module = null)
    {
        return array_merge($this->globalMiddlewares, $this->moduleMiddlewares[$module] ?? []);
    }
}