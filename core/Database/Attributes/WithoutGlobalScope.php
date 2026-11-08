<?php

namespace Ryra\Core\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class WithoutGlobalScope
{
    public readonly string $globalScope;

    public function __construct(string $globalScope)
    {
        $this->globalScope = $globalScope;
    }
}