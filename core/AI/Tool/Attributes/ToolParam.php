<?php

namespace Ryra\Core\AI\Tool\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class ToolParam
{
    /**
     * @param array<int, string|int|float>|null $enum Giới hạn giá trị hợp lệ.
     */
    public function __construct(
        public readonly string $description = '',
        public readonly ?array $enum = null,
    ) {}
}
