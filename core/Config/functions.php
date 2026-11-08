<?php

use Ryra\Core\App;

if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        $config = App::service('config');
        if ($key !== null) {
            return $config->get($key, $default);
        }
        return $config;
    }
}