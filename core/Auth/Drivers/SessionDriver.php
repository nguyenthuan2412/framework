<?php

namespace Ryra\Core\Auth\Drivers;

use Ryra\Core\App;
use Ryra\Core\Auth\Contracts\Authenticatable;
use Ryra\Core\Auth\Contracts\StatefulGuard;
use Ryra\Core\Session\Session;

/**
 * Xác thực dựa trên session (dành cho giao diện web).
 */
class SessionDriver extends AbstractDriver implements StatefulGuard
{
    /**
     * Chỉ đăng nhập cho request hiện tại, không ghi vào session.
     */
    protected bool $once = false;

    protected function resolveUser(): ?Authenticatable
    {
        $id = $this->session()?->get($this->sessionKey());

        return \is_null($id) ? null : $this->provider->retrieveById($id);
    }

    public function attempt(array $credentials = []): bool
    {
        $user = $this->retrieveByCredentials($credentials);

        if (\is_null($user)) {
            return false;
        }

        $this->login($user);

        return true;
    }

    public function once(array $credentials = []): bool
    {
        $user = $this->retrieveByCredentials($credentials);

        if (\is_null($user)) {
            return false;
        }

        $this->once = true;
        $this->setUser($user);

        return true;
    }

    public function login(Authenticatable $user): void
    {
        $this->session()?->put($this->sessionKey(), $user->getAuthIdentifier());
        $this->setUser($user);
    }

    public function loginUsingId(mixed $id): ?Authenticatable
    {
        $user = $this->provider->retrieveById($id);

        if (\is_null($user)) {
            return null;
        }

        $this->login($user);

        return $user;
    }

    public function logout(): void
    {
        if ($session = $this->session()) {
            $session->forget($this->sessionKey());
            $session->regenerateToken();
        }

        $this->once = false;
        $this->forgetUser();
    }

    /**
     * Đang ở chế độ đăng nhập một lần (không lưu session) hay không.
     */
    public function isOnce(): bool
    {
        return $this->once;
    }

    /**
     * Khoá lưu định danh người dùng trong session, tách riêng theo từng guard.
     */
    public function sessionKey(): string
    {
        return $this->config('session_key')
            ?? 'login_' . $this->name . '_' . \sha1(static::class);
    }

    /**
     * Session của request hiện tại, null nếu chưa bật middleware StartSession.
     */
    protected function session(): ?Session
    {
        if (!App::hasService('session')) {
            return null;
        }

        return App::service('session');
    }
}
