<?php

namespace Ryra\Core\AI\Tool\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Tool
{
    public function __construct(
        public readonly string $description,
        public readonly ?string $name = null,
    ) {}
}
