<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;
use Ryra\Core\ValidatedDTO\ValidatedDTO;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsAlphaNum extends Rule
{
    public function __construct(protected string $message = 'The :attribute only allows alphabet and numeric.') {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (!\is_string($value) && !\is_numeric($value)) {
            return false;
        }

        return \preg_match('/^[\pL\pM\pN]+$/u', $value) > 0;
    }
}