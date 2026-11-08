<?php

namespace Ryra\Core\AI\Message;

use Ryra\Core\Support\Arrayable;

abstract class Message implements Arrayable
{
    public function __construct(
        public readonly string $content = '',
    ) {}

    abstract public function role(): Role;

    public function isEmpty(): bool
    {
        return trim($this->content) === '';
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role()->value,
            'content' => $this->content,
        ];
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
