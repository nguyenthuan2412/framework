<?php

namespace Ryra\Core\Jwt;

use Ryra\Core\Jwt\Blacklist\BlacklistInterface;
use Ryra\Core\Jwt\Blacklist\BlacklistManager;

class JWTManager
{
    public function __construct(private BlacklistManager $blacklist) {}

    public function builder(): Builder
    {
        return new Builder();
    }

    public function config(
        string $key,
        string|array $algorithms = 'HS256',
        ?string $issuer = null,
        ?string $audience = null,
        int $leeway = 0
    ): Config
    {
        return new Config($key, (array) $algorithms, $issuer, $audience, $leeway);
    }

    public function blacklist(?string $driver = null): BlacklistInterface
    {
        return $this->blacklist->driver($driver);
    }
}
