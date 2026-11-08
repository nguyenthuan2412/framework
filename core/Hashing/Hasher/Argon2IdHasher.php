<?php

namespace Ryra\Core\Hashing\Hasher;

use RuntimeException;

class Argon2IdHasher extends ArgonHasher
{
    public function __construct(
        protected int $memoryCost = PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
        protected int $timeCost = PASSWORD_ARGON2_DEFAULT_TIME_COST,
        protected int $threads = PASSWORD_ARGON2_DEFAULT_THREADS
    ) {}

    /**
     * Hash the given value.
     * 
     * @throws \RuntimeException
     */
    public function make(string $value, array $options = []): string
    {
        $hash = \password_hash($value, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memoryCost($options),
            'time_cost' => $this->timeCost($options),
            'threads' => $this->threads($options)
        ]);

        if ($hash === false) {
            throw new RuntimeException('Argon2id hashing not supported.');
        }

        return $hash;
    }

    /**
     * Check if the given hash has been hashed using the given options.
     */
    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return \password_needs_rehash($hashedValue, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memoryCost($options),
            'time_cost' => $this->timeCost($options),
            'threads' => $this->threads($options)
        ]);
    }
}