<?php

namespace Ryra\Core\Auth\Traits;

/**
 * Cài đặt mặc định của \Ryra\Core\Auth\Contracts\Authenticatable cho model.
 */
trait Authenticatable
{
    /**
     * Tên cột chứa mật khẩu đã băm.
     */
    protected string $authPasswordName = 'password';

    public function getAuthIdentifierName(): string
    {
        return $this->getKeyName();
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getAuthPasswordName(): string
    {
        return $this->authPasswordName;
    }

    public function getAuthPassword(): ?string
    {
        $password = $this->getAttribute($this->getAuthPasswordName());

        return \is_null($password) ? null : (string) $password;
    }
}
