<?php

declare(strict_types=1);

namespace Ryra\Core\Database\Migration\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Index
{
    public function __construct(
        public readonly array $columns,
        public readonly ?string $name = null,
        public readonly bool $unique = false,
        public readonly string $type = 'INDEX',
    ) {
    }
}