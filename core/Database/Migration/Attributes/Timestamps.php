<?php

declare(strict_types=1);

namespace Ryra\Core\Database\Migration\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Timestamps
{
    public function __construct(
        public readonly string $createdAtColumn = 'created_at',
        public readonly string $updatedAtColumn = 'updated_at',
    ) {
    }
}