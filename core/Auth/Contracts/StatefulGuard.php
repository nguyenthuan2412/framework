<?php

namespace Ryra\Core\Auth\Contracts;

/**
 * Guard lưu trạng thái đăng nhập giữa các request (session).
 */
interface StatefulGuard extends Guard
{
    /**
     * Đăng nhập bằng thông tin đăng nhập.
     */
    public function attempt(array $credentials = []): bool;

    /**
     * Đăng nhập cho một request duy nhất, không lưu vào session.
     */
    public function once(array $credentials = []): bool;

    /**
     * Ghi nhận đối tượng đã đăng nhập.
     */
    public function login(Authenticatable $user): void;

    /**
     * Đăng nhập theo định danh.
     */
    public function loginUsingId(mixed $id): ?Authenticatable;

    /**
     * Đăng xuất.
     */
    public function logout(): void;
}
