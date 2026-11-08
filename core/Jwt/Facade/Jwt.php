<?php

namespace Ryra\Core\Jwt\Facade;

use Ryra\Core\Jwt\Blacklist\BlacklistInterface;
use Ryra\Core\Jwt\Builder;
use Ryra\Core\Jwt\Config;
use Ryra\Core\Support\Facade;

/**
 * @method static Builder builder()
 * @method static Config config(string $key, string|array $algorithms = 'HS256', ?string $issuer = null, ?string $audience = null, int $leeway = 0)
 * @method static BlacklistInterface blacklist(?string $driver = null)
 *
 * @see \Ryra\Core\Jwt\JWTManager
 */
class Jwt extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'jwt';
    }
}
