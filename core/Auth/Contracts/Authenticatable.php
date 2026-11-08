<?php

namespace Ryra\Core\Auth\Contracts;

/**
 * Đối tượng có thể được xác thực (admin, hướng dẫn viên, khách hàng...).
 */
interface Authenticatable
{
    /**
     * Tên cột định danh của đối tượng (thường là khoá chính).
     */
    public function getAuthIdentifierName(): string;

    /**
     * Giá trị định danh của đối tượng.
     */
    public function getAuthIdentifier(): mixed;

    /**
     * Tên cột chứa mật khẩu đã được băm.
     */
    public function getAuthPasswordName(): string;

    /**
     * Mật khẩu đã được băm.
     */
    public function getAuthPassword(): ?string;
}
