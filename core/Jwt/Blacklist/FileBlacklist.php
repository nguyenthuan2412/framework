<?php

namespace Ryra\Core\Jwt\Blacklist;

use Ryra\Core\Jwt\Exception\JWTException;
use Ryra\Core\Jwt\Token;

class FileBlacklist implements BlacklistInterface
{
    private string $path;

    public function __construct(string $storagePath = '')
    {
        $this->path = rtrim($storagePath ?: \sys_get_temp_dir(), '/') . '/jwt_blacklist.json';
    }

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

        if ($exp <= \time()) {
            return;
        }

        $data = $this->read();
        $data[$jti] = $exp;
        $this->write($data);
    }

    public function has(Token $token): bool
    {
        $jti = $token->identifiedBy();
        if ($jti === null) {
            return false;
        }

        $data = $this->read();

        if (!isset($data[$jti])) {
            return false;
        }

        if ($data[$jti] <= \time()) {
            unset($data[$jti]);
            $this->write($data);
            return false;
        }

        return true;
    }

    public function flush(): void
    {
        $now = \time();
        $data = \array_filter($this->read(), static fn(int $exp) => $exp > $now);
        $this->write($data);
    }

    private function read(): array
    {
        if (!\file_exists($this->path)) {
            return [];
        }

        $content = \file_get_contents($this->path);
        return $content ? (\json_decode($content, true) ?? []) : [];
    }

    private function write(array $data): void
    {
        \file_put_contents($this->path, \json_encode($data), LOCK_EX);
    }
}
