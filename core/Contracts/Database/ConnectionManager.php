<?php

namespace Ryra\Core\Contracts\Database;

interface ConnectionManager
{
    public function addConnection(string $name, array $config): void;

    public function getConnection(?string $name = null): Connection;

    public function getConfig(?string $name = null): ?array;

    public function setDefaultConnection(string $defaultConnection): static;

    public function getDefaultConnection(): ?string;

    public function disconnect(): void;
}