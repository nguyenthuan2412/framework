<?php

namespace Ryra\Core\Auth\Drivers;

use Ryra\Core\App;
use Ryra\Core\Auth\Contracts\Authenticatable;
use Ryra\Core\Auth\Contracts\Guard;
use Ryra\Core\Auth\Contracts\UserProvider;
use Ryra\Core\Auth\Exception\AuthenticationException;
use Ryra\Core\Collection\Arr;
use Ryra\Core\Http\Request;

abstract class AbstractDriver implements Guard
{
    protected ?Authenticatable $user = null;

    /**
     * Đã cố gắng phân giải người dùng cho request hiện tại hay chưa.
     */
    protected bool $resolved = false;

    public function __construct(
        protected string $name,
        protected UserProvider $provider,
        protected array $config = [],
    ) {}

    /**
     * Phân giải người dùng từ request hiện tại.
     */
    abstract protected function resolveUser(): ?Authenticatable;

    public function getName(): string
    {
        return $this->name;
    }

    public function user(): ?Authenticatable
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;

        return $this->user = $this->resolveUser();
    }

    public function authenticate(): Authenticatable
    {
        if ($user = $this->user()) {
            return $user;
        }

        throw new AuthenticationException(guard: $this->name);
    }

    public function check(): bool
    {
        return !\is_null($this->user());
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function hasUser(): bool
    {
        return !\is_null($this->user);
    }

    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;
        $this->resolved = true;

        return $this;
    }

    /**
     * Quên người dùng đã phân giải, lần gọi user() sau sẽ phân giải lại.
     */
    public function forgetUser(): static
    {
        $this->user = null;
        $this->resolved = false;

        return $this;
    }

    public function validate(array $credentials = []): bool
    {
        return !\is_null($this->retrieveByCredentials($credentials));
    }

    public function getProvider(): UserProvider
    {
        return $this->provider;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Tìm người dùng khớp thông tin đăng nhập, null nếu sai tài khoản/mật khẩu.
     */
    protected function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials)) {
            return null;
        }

        $user = $this->provider->retrieveByCredentials($credentials);

        if (\is_null($user) || !$this->provider->validateCredentials($user, $credentials)) {
            return null;
        }

        $this->provider->rehashPasswordIfRequired($user, $credentials);

        return $user;
    }

    /**
     * Lấy giá trị cấu hình của guard, hỗ trợ "dot notation".
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    /**
     * Request của coroutine hiện tại.
     */
    protected function request(): Request
    {
        return App::service('request');
    }
}
