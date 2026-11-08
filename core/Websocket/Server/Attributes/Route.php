<?php

namespace Ryra\Core\Websocket\Server\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Route
{
    public function __construct(public string $uri) {}

    public function getUri(): string
    {
        return $this->uri;
    }
}