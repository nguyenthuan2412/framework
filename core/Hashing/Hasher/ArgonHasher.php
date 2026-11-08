<?php

namespace Ryra\Core\Hashing\Hasher;

use RuntimeException;

class ArgonHasher extends AbstractHasher
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
        $hash = \password_hash($value, PASSWORD_ARGON2I, [
            'memory_cost' => $this->memoryCost($options),
            'time_cost' => $this->timeCost($options),
            'threads' => $this->threads($options)
        ]);

        if ($hash === false) {
            throw new RuntimeException('Argon2 hashing not supported.');
        }

        return $hash;
    }

    /**
     * Check if the given hash has been hashed using the given options.
     */
    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return \password_needs_rehash($hashedValue, PASSWORD_ARGON2I, [
            'memory_cost' => $this->memoryCost($options),
            'time_cost' => $this->timeCost($options),
            'threads' => $this->threads($options)
        ]);
    }

    public function setThreads(int $threads): static
    {
        $this->threads = $threads;
        return $this;
    }

    public function setTimeCost(int $timeCost): static
    {
        $this->timeCost = $timeCost;
        return $this;
    }

    public function setMemoryCost(int $memoryCost): static
    {
        $this->memoryCost = $memoryCost;
        return $this;
    }

    public function threads(array $options = []): int
    {
        return $options['threads'] ?? $this->threads;
    }

    public function timeCost(array $options = []): int
    {
        return $options['time_cost'] ?? $this->timeCost;
    }

    public function memoryCost(array $options = []): int
    {
        return $options['memory_cost'] ?? $this->memoryCost;
    }
}