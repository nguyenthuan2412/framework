<?php

namespace Ryra\Core\Container;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY|Attribute::TARGET_PARAMETER)]
class Inject
{
    public function __construct(protected string $id = '') {}

    public function __toString(): string
    {
        return $this->id;
    }
}