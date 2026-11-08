<?php

namespace Ryra\Core\Contracts\Config;

interface Repository
{
    /**
     * Get config by name.
     */
    public function get(string $name, mixed $default = null): mixed;

    /**
     * Set config.
     */
    public function set(string $name, mixed $config): static;

    /**
     * Set original config.
     */
    public function setOriginal(string $name, mixed $config): static;

    /**
     * Check has config.
     */
    public function has(string $name): bool;

    /**
     * Copy config.
     *
     * @throws \Ryra\Core\Config\Exception\ConfigNotFoundException If config file not found
     */
    public function copy(string $from, string $to): bool;
}