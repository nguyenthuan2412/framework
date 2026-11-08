<?php

declare(strict_types=1);

namespace Ryra\Core\Database\Migration\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        public readonly string $type = 'string',
        public readonly ?int $length = null,
        public readonly bool $nullable = false,
        public readonly mixed $default = null,
        public readonly bool $unique = false,
        public readonly bool $unsigned = false,
        public readonly ?string $comment = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
    ) {
    }
}