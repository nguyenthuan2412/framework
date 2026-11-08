<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Rule;
use Ryra\Core\ValidatedDTO\Property;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsString extends Rule
{
    public function __construct(protected string $message = 'The :attribute must be string') {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        return \is_string($value);
    }
}