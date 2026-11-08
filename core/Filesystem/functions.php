<?php

use Ryra\Core\App;

function public_path(string $path = ''): string
{
    return App::service('filesystem')->path($path);
}

function storage_path(?string $path = null): string
{
    return App::getInstance()->storagePath($path);
}

function join_paths(string ...$paths): string
{
    $paths = array_filter($paths, fn ($path) => !empty($path));
    return join(DS, $paths);
}