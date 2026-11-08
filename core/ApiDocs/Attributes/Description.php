<?php

namespace Ryra\Core\ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Description
{
    public function __construct(
        protected string $description
    ) {}

    public function __toString(): string
    {
        return $this->description;
    }
}
