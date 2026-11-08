<?php

namespace Ryra\Core\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class FindBy
{
    public function __construct(protected string $field) {}

    public function __toString(): string
    {
        return $this->field;
    }
}