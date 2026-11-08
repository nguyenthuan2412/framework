<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsBase64 extends Rule
{
    public function __construct(
        protected string $message = 'The :attribute must be a valid Base64 string.'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (!\is_string($value)) {
            return false;
        }

        if (!\preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $value)) {
            return false;
        }

        return \base64_encode(\base64_decode($value, true)) === $value;
    }
}
