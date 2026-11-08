<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsMacAddress extends Rule
{
    public function __construct(protected string $message = 'The :attribute is not a valid MAC address.') {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (!\is_string($value)) {
            return false;
        }

        // Support both colon and hyphen separators: 00:1A:2B:3C:4D:5E or 00-1A-2B-3C-4D-5E
        $pattern = '/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/';

        return \preg_match($pattern, $value) === 1;
    }
}
