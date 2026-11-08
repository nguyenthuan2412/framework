<?php

namespace Ryra\Core\Auth\Contracts;

/**
 * Cho phép model tuỳ biến claim khi phát hành JWT.
 */
interface JwtSubject
{
    /**
     * Giá trị được đưa vào claim "sub".
     */
    public function getJwtIdentifier(): string|int;

    /**
     * Các claim tuỳ biến được nhúng thêm vào token (email, name, role...).
     */
    public function getJwtCustomClaims(): array;
}
