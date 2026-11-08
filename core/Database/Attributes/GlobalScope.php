<?php

namespace Ryra\Core\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS|Attribute::TARGET_METHOD)]
class GlobalScope
{
    public readonly array $scopes;

    public function __construct(string ...$scopes)
    {
        $this->scopes = $scopes;
    }
}