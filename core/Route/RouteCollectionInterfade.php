<?php

namespace Ryra\Core\Route;

use Ryra\Core\Module\Module;

interface RouteCollectionInterfade
{
    public function addRoute($httpMethod, $uri, $options): void;

    public function getUrl(string $name, array $params = [], bool $absolute = true, string|int|null $version = null): ?string;

    public function register(string $controller, ?Module $module = null): void;
}