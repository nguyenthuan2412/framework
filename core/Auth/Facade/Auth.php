<?php

namespace Ryra\Core\Auth\Facade;

use Ryra\Core\App;
use Ryra\Core\Auth\Contracts\Authenticatable;
use Ryra\Core\Auth\Contracts\Guard;
use Ryra\Core\Auth\Contracts\UserProvider;
use Ryra\Core\Support\Facade;

/**
 * @method static bool check()
 * @method static bool guest()
 * @method static Authenticatable|null user()
 * @method static Authenticatable authenticate()
 * @method static mixed id()
 * @method static bool validate(array $credentials = [])
 * @method static bool hasUser()
 * @method static Guard setUser(Authenticatable $user)
 * @method static UserProvider getProvider()
 * @method static void shouldUse(?string $name)
 * @method static string getDefaultDriver()
 * @method static UserProvider createUserProvider(?string $name = null)
 * @method static \Ryra\Core\Auth\AuthManager extend(string $driver, \Closure $callback)
 * @method static \Ryra\Core\Auth\AuthManager provider(string $driver, \Closure $callback)
 * @method static \Ryra\Core\Auth\AuthManager forgetGuards()
 *
 * @see \Ryra\Core\Auth\AuthManager
 */
class Auth extends Facade
{
    protected static function getFacadeAccessor(): string|object
    {
        return 'auth';
    }

    /**
     * Lấy guard theo tên, mặc định lấy guard trong config auth.defaults.guard.
     */
    public static function guard(?string $name = null): Guard
    {
        return App::service('auth')->guard($name);
    }
}
