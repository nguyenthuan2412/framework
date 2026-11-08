<?php

namespace Ryra\Core\Auth;

use Closure;
use Ryra\Core\App;
use Ryra\Core\Auth\Contracts\Guard;
use Ryra\Core\Auth\Contracts\UserProvider;
use Ryra\Core\Auth\Drivers\BasicDriver;
use Ryra\Core\Auth\Drivers\BearerDriver;
use Ryra\Core\Auth\Drivers\JwtDriver;
use Ryra\Core\Auth\Drivers\SessionDriver;
use Ryra\Core\Auth\Exception\InvalidGuardException;
use Ryra\Core\Auth\Providers\ModelUserProvider;
use Ryra\Core\Support\Str;

/**
 * Quản lý các guard xác thực được khai báo trong config/auth.php.
 *
 * @mixin \Ryra\Core\Auth\Contracts\Guard
 */
class AuthManager
{
    /**
     * Các guard đã khởi tạo trong phạm vi request hiện tại.
     *
     * @var array<string, Guard>
     */
    protected array $guards = [];

    /**
     * @var array<string, Closure>
     */
    protected array $customDrivers = [];

    /**
     * @var array<string, Closure>
     */
    protected array $customProviders = [];

    protected ?string $defaultGuard = null;

    public function __construct(protected App $app) {}

    /**
     * Lấy guard theo tên, mặc định lấy guard trong config auth.defaults.guard.
     */
    public function guard(?string $name = null): Guard
    {
        $name ??= $this->getDefaultDriver();

        return $this->guards[$name] ??= $this->resolve($name);
    }

    /**
     * Đổi guard mặc định cho request hiện tại.
     */
    public function shouldUse(?string $name): void
    {
        $this->defaultGuard = $name ?? $this->getDefaultDriver();
    }

    public function getDefaultDriver(): string
    {
        return $this->defaultGuard ?? config('auth.defaults.guard', 'api');
    }

    public function setDefaultDriver(string $name): void
    {
        $this->defaultGuard = $name;
    }

    /**
     * Đăng ký thêm một driver guard tuỳ biến.
     *
     * Callback nhận vào (string $name, UserProvider $provider, array $config, App $app).
     */
    public function extend(string $driver, Closure $callback): static
    {
        $this->customDrivers[$driver] = $callback;

        return $this;
    }

    /**
     * Đăng ký thêm một driver user provider tuỳ biến.
     *
     * Callback nhận vào (array $config, App $app).
     */
    public function provider(string $driver, Closure $callback): static
    {
        $this->customProviders[$driver] = $callback;

        return $this;
    }

    /**
     * Xoá các guard đã khởi tạo (dùng khi kết thúc request hoặc trong test).
     */
    public function forgetGuards(): static
    {
        $this->guards = [];
        $this->defaultGuard = null;

        return $this;
    }

    /**
     * Khởi tạo user provider theo tên khai báo trong auth.providers.
     */
    public function createUserProvider(?string $name = null): UserProvider
    {
        $name ??= config('auth.defaults.provider');
        $config = config("auth.providers.{$name}");

        if (empty($config)) {
            throw new InvalidGuardException("Auth user provider [{$name}] is not defined.");
        }

        $driver = $config['driver'] ?? 'model';

        if (isset($this->customProviders[$driver])) {
            return ($this->customProviders[$driver])($config, $this->app);
        }

        return match ($driver) {
            'model' => $this->createModelProvider($config),
            default => throw new InvalidGuardException("Auth user provider driver [{$driver}] is not supported."),
        };
    }

    /**
     * Khởi tạo guard theo cấu hình.
     */
    protected function resolve(string $name): Guard
    {
        $config = config("auth.guards.{$name}");

        if (empty($config)) {
            throw new InvalidGuardException("Auth guard [{$name}] is not defined.");
        }

        if (empty($config['driver'])) {
            throw new InvalidGuardException("Auth guard [{$name}] does not have a driver.");
        }

        $driver = $config['driver'];
        $provider = $this->createUserProvider($config['provider'] ?? null);

        if (isset($this->customDrivers[$driver])) {
            return ($this->customDrivers[$driver])($name, $provider, $config, $this->app);
        }

        $method = 'create' . Str::studly($driver) . 'Driver';

        if (!\method_exists($this, $method)) {
            throw new InvalidGuardException("Auth guard driver [{$driver}] is not supported.");
        }

        return $this->{$method}($name, $provider, $config);
    }

    protected function createJwtDriver(string $name, UserProvider $provider, array $config): JwtDriver
    {
        return new JwtDriver(
            $name,
            $provider,
            \array_merge((array) config('auth.jwt', []), $config),
            $this->app->get('jwt')
        );
    }

    protected function createBearerDriver(string $name, UserProvider $provider, array $config): BearerDriver
    {
        return new BearerDriver($name, $provider, $config);
    }

    protected function createBasicDriver(string $name, UserProvider $provider, array $config): BasicDriver
    {
        return new BasicDriver($name, $provider, $config);
    }

    protected function createSessionDriver(string $name, UserProvider $provider, array $config): SessionDriver
    {
        return new SessionDriver($name, $provider, $config);
    }

    protected function createModelProvider(array $config): ModelUserProvider
    {
        if (empty($config['model'])) {
            throw new InvalidGuardException('Auth user provider of driver [model] requires a model class.');
        }

        return new ModelUserProvider(
            $config['model'],
            $this->app->get('hash')->driver($config['hash'] ?? null),
            $config['conditions'] ?? []
        );
    }

    /**
     * Chuyển tiếp các lời gọi còn lại tới guard mặc định.
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->guard()->{$method}(...$arguments);
    }
}
