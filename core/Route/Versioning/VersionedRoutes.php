<?php

namespace Ryra\Core\Route\Versioning;

use Ryra\Core\Route\Route;

/**
 * Nhiều version cùng dùng chung một (method, uri) - trường hợp versioning theo
 * header/media type/custom. FastRoute chỉ giữ được một handler cho mỗi uri nên
 * handler thực tế là object này, việc chọn route theo version làm ở dispatcher.
 */
class VersionedRoutes
{
    /**
     * @var array<int, Route>
     */
    protected array $routes = [];

    public function __construct(?Route $route = null)
    {
        if ($route !== null) {
            $this->add($route);
        }
    }

    public function add(Route $route): static
    {
        $this->routes[] = $route;

        return $this;
    }

    /**
     * @return array<int, Route>
     */
    public function all(): array
    {
        return $this->routes;
    }

    public function first(): ?Route
    {
        return $this->routes[0] ?? null;
    }

    /**
     * Chọn route khớp với version của request.
     *
     * @param array<int, string>|string|null $versions null = không match theo version
     */
    public function match(array|string|null $versions): ?Route
    {
        if ($versions === null) {
            return $this->first();
        }

        foreach ((array) $versions as $version) {
            foreach ($this->routes as $route) {
                if ($route->getVersion() === (string) $version) {
                    return $route;
                }
            }
        }

        foreach ($this->routes as $route) {
            if ($route->isVersionNeutral()) {
                return $route;
            }
        }

        return null;
    }
}
