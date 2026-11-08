<?php

use Ryra\Core\App;
use Ryra\Core\Module\Exception\ModuleNotFound;
use Ryra\Core\Url\Url;

if (!function_exists('asset')) {
    function asset(string $path, string $module): string
    {
        $moduleManager = App::service('module.manager');
        if ($moduleManager->has($module)) {
            return Url::base()->withPath("assets/$module/$path");
        }

        throw new ModuleNotFound($module);
    }
}

if (!function_exists('asset_path')) {
    function asset_path(string $path, string $module): string
    {
        $moduleManager = App::service('module.manager');
        if ($moduleManager->has($module)) {
            return $moduleManager->get($module)->getPath() . "/assets/$path";
        }

        throw new ModuleNotFound($module);
    }
}