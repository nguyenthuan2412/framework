<?php

namespace Ryra\Core\Route\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Patch extends Get
{
    public function getMethod(): string
    {
        return 'PATCH';
    }
}