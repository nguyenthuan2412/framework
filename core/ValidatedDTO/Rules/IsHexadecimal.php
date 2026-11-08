<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsHexadecimal extends Rule
{
    public function __construct(
        protected string $message = 'The :attribute must be a valid hexadecimal string.'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        return \is_string($value) && (bool) \preg_match('/^(0x)?[0-9a-fA-F]+$/', $value);
    }
}
