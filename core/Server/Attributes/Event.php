<?php

namespace Ryra\Core\Server\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Event
{
    public function __construct(protected string $event) {}

    public function __toString()
    {
        return $this->event;
    }
}