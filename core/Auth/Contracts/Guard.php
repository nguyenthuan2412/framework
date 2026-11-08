<?php

namespace Ryra\Core\Auth\Contracts;

use Ryra\Core\Auth\Exception\AuthenticationException;

interface Guard
{
    /**
     * Tên guard đang sử dụng (admin, guide, api...).
     */
    public function getName(): string;

    /**
     * Đã đăng nhập hay chưa.
     */
    public function check(): bool;

    /**
     * Là khách (chưa đăng nhập) hay không.
     */
    public function guest(): bool;

    /**
     * Đối tượng đang đăng nhập, null nếu chưa xác thực.
     */
    public function user(): ?Authenticatable;

    /**
     * Lấy đối tượng đang đăng nhập, ném lỗi 401 nếu chưa xác thực.
     *
     * @throws AuthenticationException
     */
    public function authenticate(): Authenticatable;

    /**
     * Định danh của đối tượng đang đăng nhập.
     */
    public function id(): mixed;

    /**
     * Kiểm tra thông tin đăng nhập mà không ghi nhận phiên đăng nhập.
     */
    public function validate(array $credentials = []): bool;

    /**
     * Đã có đối tượng được phân giải trong guard hay chưa.
     */
    public function hasUser(): bool;

    /**
     * Gán trực tiếp đối tượng đang đăng nhập.
     */
    public function setUser(Authenticatable $user): static;

    /**
     * Nguồn dữ liệu người dùng của guard.
     */
    public function getProvider(): UserProvider;
}
