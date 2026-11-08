<?php

namespace Ryra\Core\Route;

use Ryra\Core\App;
use Ryra\Core\Context\Context;
use Ryra\Core\Module\Module;

class Route
{
    protected array $action;

    protected ?string $name = null;

    protected ?Module $module = null;

    protected array $middlewares = [];

    /**
     * Version của route, null nghĩa là version neutral.
     */
    protected ?string $version = null;

    public function __construct(array $config)
    {
        $this->action = $config['action'];
        $this->name = $config['name'] ?? null;
        $this->module = $config['module'] ?? null;
        $this->version = isset($config['version']) ? (string) $config['version'] : null;
        $middlewares = \array_merge(
            App::service('middleware.collection')->all(),
            App::service('middleware.collection')->all($this->module?->getId()),
            $config['middlewares'] ?? []
        );
        $middlewares = \array_diff($middlewares, $config['excluded_middlewares'] ?? []);
        $this->middlewares = $middlewares;
    }

    public function hasName(): bool
    {
        return !\is_null($this->name);
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getAction(): array
    {
        return $this->action;
    }

    public function getModule(): ?Module
    {
        return $this->module;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function isVersionNeutral(): bool
    {
        return \is_null($this->version);
    }

    public function matchesVersion(string|int|null $version): bool
    {
        return $this->isVersionNeutral() || $this->version === (string) $version;
    }

    public function getParams(): array
    {
        return Context::get('__route.params', []);
    }

    public function getParam(string $key, mixed $value = null): mixed
    {
        return Context::get("__route.params.$key", $value);
    }
}