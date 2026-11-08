<?php

declare(strict_types=1);

namespace Ryra\Core\Database\Migration\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ForeignKey
{
    public function __construct(
        public readonly array $columns,
        public readonly string $references,
        public readonly string $onDelete = 'CASCADE',
        public readonly string $onUpdate = 'CASCADE',
        public readonly ?string $name = null,
    ) {
    }
}