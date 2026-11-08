<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsBefore extends Rule
{
    public function __construct(protected string $message = 'The :attribute may only allows alphabet and spaces.') {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (!\is_string($value)) {
            return false;
        }

        return \preg_match('/^[\pL\pM\s]+$/u', $value) > 0;
    }
}