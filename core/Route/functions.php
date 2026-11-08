<?php

use Ryra\Core\App;

function route(string $name, array $params = [], bool $absolute = true, string|int|null $version = null): ?string
{
    return App::service('route.collection')->getUrl($name, $params, $absolute, $version);
}
