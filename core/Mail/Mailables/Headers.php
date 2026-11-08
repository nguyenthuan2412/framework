<?php

namespace Ryra\Core\Mail\Mailables;

class Headers
{
    public function __construct(
        public readonly ?string $messageId = null,
        public readonly array $references = [],
        public readonly array $text = []
    ) {}
}