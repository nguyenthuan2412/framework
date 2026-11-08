<?php

namespace Ryra\Core\Auth\Contracts;

/**
 * Nguồn lấy dữ liệu người dùng cho các guard (model, mảng, api...).
 */
interface UserProvider
{
    /**
     * Lấy đối tượng theo định danh.
     */
    public function retrieveById(mixed $identifier): ?Authenticatable;

    /**
     * Lấy đối tượng theo các thông tin đăng nhập (không gồm password).
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable;

    /**
     * Kiểm tra mật khẩu của đối tượng có khớp thông tin đăng nhập hay không.
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool;

    /**
     * Băm lại mật khẩu nếu cấu hình hashing đã thay đổi.
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void;

    /**
     * Cập nhật một thuộc tính dùng cho xác thực (api_token, remember_token...).
     */
    public function updateAuthAttribute(Authenticatable $user, string $key, mixed $value): void;
}
