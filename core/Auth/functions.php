<?php

use Ryra\Core\App;
use Ryra\Core\Auth\AuthManager;
use Ryra\Core\Auth\Contracts\Guard;

if (!function_exists('auth')) {
    /**
     * Lấy manager xác thực, hoặc guard cụ thể khi truyền tên guard.
     */
    function auth(?string $guard = null): AuthManager|Guard
    {
        $auth = App::service('auth');

        return is_null($guard) ? $auth : $auth->guard($guard);
    }
}
