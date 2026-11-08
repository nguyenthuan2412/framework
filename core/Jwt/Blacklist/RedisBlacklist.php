<?php

namespace Ryra\Core\Jwt\Blacklist;

use Ryra\Core\Jwt\Exception\JWTException;
use Ryra\Core\Jwt\Token;
use Ryra\Core\Redis\Connections\Connection;

class RedisBlacklist implements BlacklistInterface
{
    private const PREFIX = 'jwt:blacklist:';

    public function __construct(private Connection $redis) {}

    public function add(Token $token): void
    {
        $jti = $token->identifiedBy();
        if ($jti === null) {
            throw new JWTException('Token must have a JTI claim to be blacklisted.');
        }

        $exp = $token->expiresAt();
        if ($exp === null) {
            throw new JWTException('Token must have an expiration time to be blacklisted.');
        }

        $ttl = $exp - time();
        if ($ttl <= 0) {
            return; // token đã hết hạn, không cần blacklist
        }

        $this->redis->setex(self::PREFIX . $jti, $ttl, '1');
    }

    public function has(Token $token): bool
    {
        $jti = $token->identifiedBy();
        if ($jti === null) {
            return false;
        }

        return (bool) $this->redis->exists(self::PREFIX . $jti);
    }

    public function flush(): void
    {
        $cursor = null;
        do {
            [$cursor, $keys] = $this->redis->scan($cursor, self::PREFIX . '*', 100);
            if (!empty($keys)) {
                $this->redis->del(...$keys);
            }
        } while ($cursor !== 0);
    }

}
