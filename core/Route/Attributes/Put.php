<?php

namespace Ryra\Core\Route\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Put extends Get
{
    public function getMethod(): string
    {
        return 'PUT';
    }
}