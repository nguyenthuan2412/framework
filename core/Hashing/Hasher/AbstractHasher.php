<?php

namespace Ryra\Core\Hashing\Hasher;

class AbstractHasher
{
    public function info(string $hashed): array
    {
        return \password_get_info($hashed);
    }

    public function check(string $value, string $hashedValue): bool
    {
        if (\is_null($hashedValue) || \strlen($hashedValue) === 0) {
            return false;
        }

        return \password_verify($value, $hashedValue);
    }
}