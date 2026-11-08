<?php

namespace Ryra\Core\Hashing\Hasher;

use RuntimeException;

class BcryptHasher extends AbstractHasher
{
    public function __construct(protected int $rounds = PASSWORD_BCRYPT_DEFAULT_COST) {}

    /**
     * Hash the given value.
     * 
     * @throws \RuntimeException
     */
    public function make(string $value, array $options = []): string
    {
        $hash = \password_hash($value, PASSWORD_BCRYPT, [
            'cost' => $this->cost($options),
        ]);

        if ($hash === false) {
            throw new RuntimeException('Bcrypt hashing not supported.');
        }

        return $hash;
    }

    /**
     * Check if the given hash has been hashed using the given options.
     */
    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return \password_needs_rehash($hashedValue, PASSWORD_BCRYPT, [
            'cost' => $this->cost($options),
        ]);
    }

    public function setRounds(int $rounds): static
    {
        $this->rounds = $rounds;
        return $this;
    }

    public function cost(array $options = []): int
    {
        return $options['rounds'] ?? $this->rounds;
    }
}