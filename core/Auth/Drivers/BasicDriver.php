<?php

namespace Ryra\Core\Auth\Drivers;

use Ryra\Core\Auth\Contracts\Authenticatable;
use Ryra\Core\Auth\Exception\AuthenticationException;

/**
 * Xác thực bằng HTTP Basic (header "Authorization: Basic base64(user:pass)").
 */
class BasicDriver extends AbstractDriver
{
    protected function resolveUser(): ?Authenticatable
    {
        $credentials = $this->credentialsFromRequest();

        if (\is_null($credentials)) {
            return null;
        }

        return $this->retrieveByCredentials($credentials);
    }

    /**
     * Trả về lỗi 401 kèm challenge để trình duyệt hiện hộp thoại đăng nhập.
     */
    public function authenticate(): Authenticatable
    {
        if ($user = $this->user()) {
            return $user;
        }

        throw new AuthenticationException(
            guard: $this->name,
            headers: ['WWW-Authenticate' => \sprintf('Basic realm="%s"', $this->config('realm', 'Restricted'))],
        );
    }

    /**
     * Tách tài khoản và mật khẩu từ header Authorization.
     */
    protected function credentialsFromRequest(): ?array
    {
        $header = (string) $this->request()->header('Authorization', '');

        if (!\str_starts_with(\strtolower($header), 'basic ')) {
            return null;
        }

        $decoded = \base64_decode(\substr($header, 6), true);

        if ($decoded === false || !\str_contains($decoded, ':')) {
            return null;
        }

        [$username, $password] = \explode(':', $decoded, 2);

        if ($username === '' || $password === '') {
            return null;
        }

        return [
            $this->config('username_field', 'email') => $username,
            'password' => $password,
        ];
    }
}
