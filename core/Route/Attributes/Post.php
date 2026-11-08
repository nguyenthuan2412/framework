<?php

namespace Ryra\Core\Route\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Post extends Get
{
    public function getMethod(): string
    {
        return 'POST';
    }
}