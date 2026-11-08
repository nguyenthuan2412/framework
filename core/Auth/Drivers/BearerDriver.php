<?php

namespace Ryra\Core\Auth\Drivers;

use Ryra\Core\Auth\Contracts\Authenticatable;
use Ryra\Core\Auth\Contracts\TokenGuard;
use Ryra\Core\Support\Str;

/**
 * Xác thực bằng API token lưu trong cơ sở dữ liệu.
 *
 * Token được lấy theo thứ tự: header "Authorization: Bearer", header tuỳ chọn
 * (mặc định X-Api-Token), query string / body.
 */
class BearerDriver extends AbstractDriver implements TokenGuard
{
    protected function resolveUser(): ?Authenticatable
    {
        $token = $this->tokenForRequest();

        if (empty($token)) {
            return null;
        }

        return $this->provider->retrieveByCredentials([
            $this->storageKey() => $this->hashToken($token),
        ]);
    }

    /**
     * Xác thực thông tin đăng nhập và cấp API token mới.
     */
    public function attempt(array $credentials = []): ?string
    {
        $user = $this->retrieveByCredentials($credentials);

        if (\is_null($user)) {
            return null;
        }

        return $this->login($user);
    }

    /**
     * Cấp API token mới cho người dùng và ghi đè token cũ.
     */
    public function login(Authenticatable $user): string
    {
        $token = Str::random((int) $this->config('length', 60));

        $this->provider->updateAuthAttribute($user, $this->storageKey(), $this->hashToken($token));
        $this->setUser($user);

        return $token;
    }

    /**
     * Thu hồi token của người dùng hiện tại.
     */
    public function logout(): void
    {
        if ($user = $this->user()) {
            $this->provider->updateAuthAttribute($user, $this->storageKey(), null);
        }

        $this->forgetUser();
    }

    public function token(): ?string
    {
        return $this->tokenForRequest();
    }

    /**
     * Lấy token thô từ request.
     */
    protected function tokenForRequest(): ?string
    {
        $request = $this->request();

        if ($token = $request->bearerToken()) {
            return $token;
        }

        if ($header = $this->config('header', 'X-Api-Token')) {
            if ($token = $request->header($header)) {
                return (string) $token;
            }
        }

        if ($inputKey = $this->config('input_key')) {
            if ($token = $request->input($inputKey)) {
                return (string) $token;
            }
        }

        return null;
    }

    /**
     * Token được lưu dưới dạng băm khi bật cấu hình "hash".
     */
    protected function hashToken(string $token): string
    {
        return $this->config('hash', false) ? \hash('sha256', $token) : $token;
    }

    /**
     * Cột chứa API token trong bảng dữ liệu.
     */
    protected function storageKey(): string
    {
        return (string) $this->config('storage_key', 'api_token');
    }
}
