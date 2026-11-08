<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsIP extends Rule
{
    public function __construct(protected string $message = 'The :attribute is not valid IP Address') {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }
}
