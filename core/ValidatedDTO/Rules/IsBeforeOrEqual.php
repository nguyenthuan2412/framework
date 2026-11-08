<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;
use Ryra\Core\ValidatedDTO\ValidatedDTO;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsBeforeOrEqual extends Rule
{
    public function __construct(
        protected string $time,
        protected string $message = 'The :attribute may only allows alphabet and spaces.'
    ) {}

    public function check(mixed $value, array $values, Property $property): bool
    {
        
    }
}