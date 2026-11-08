<?php

namespace Ryra\Core\Container;

use Attribute;
use Closure;

#[Attribute(Attribute::TARGET_CLASS)]
class Scope
{
    const TRANSIENT = 'transient';
    const SINGLETON = 'singleton';
    const SCOPED = 'scoped';

    public function __construct(protected string $type, protected array|Closure $paramsOrCallback)
    {
    }
}