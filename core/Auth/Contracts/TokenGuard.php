<?php

namespace Ryra\Core\Auth\Contracts;

/**
 * Guard xác thực bằng token (jwt, bearer...), không phụ thuộc session.
 */
interface TokenGuard extends Guard
{
    /**
     * Xác thực thông tin đăng nhập và phát hành token, null nếu thất bại.
     */
    public function attempt(array $credentials = []): ?string;

    /**
     * Phát hành token cho một đối tượng.
     */
    public function login(Authenticatable $user): string;

    /**
     * Token đang được gửi lên trong request hiện tại.
     */
    public function token(): ?string;

    /**
     * Thu hồi token của request hiện tại.
     */
    public function logout(): void;
}
