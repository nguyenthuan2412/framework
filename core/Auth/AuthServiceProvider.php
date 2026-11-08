<?php

namespace Ryra\Core\Auth;

use Ryra\Core\Auth\Contracts\Guard;
use Ryra\Core\Container\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Mỗi coroutine (mỗi request) có một manager riêng để trạng thái
        // đăng nhập không bị dùng chung giữa các request.
        $this->app->scoped('auth', fn($app) => new AuthManager($app));
        $this->app->alias('auth', AuthManager::class);

        $this->app->scoped('auth.driver', fn($app) => $app->get('auth')->guard());
        $this->app->alias('auth.driver', Guard::class);
    }
}
