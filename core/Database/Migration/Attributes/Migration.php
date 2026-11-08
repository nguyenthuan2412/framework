<?php

declare(strict_types=1);

namespace Ryra\Core\Database\Migration\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Migration
{
    public function __construct(
        public readonly string $table,
        public readonly ?string $connection = null,
        public readonly string $engine = 'InnoDB',
        public readonly string $charset = 'utf8mb4',
        public readonly string $collation = 'utf8mb4_unicode_ci',
        public readonly string $comment = '',
    ) {
    }
}