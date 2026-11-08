<?php

namespace Ryra\Core\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class WithoutGlobalScopes
{
    public readonly array $globalScopes;

    public function __construct(string|array $globalScopes)
    {
        $this->globalScopes = (array) $globalScopes;
    }
}