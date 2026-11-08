<?php

declare(strict_types=1);

namespace Ryra\Core\Database\Migration\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class SoftDelete
{
    public function __construct(
        public readonly string $column = 'deleted_at',
    ) {
    }
}