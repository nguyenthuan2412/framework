<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsLowercase extends Rule
{
    public function __construct(
        protected bool $transform = false,
        protected string $message = 'The :attribute must be lowercase'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (!\is_string($value)) return false;

        $lower = \mb_strtolower($value, \mb_detect_encoding($value));

        if ($this->transform) {
            $value = $lower;
            return true;
        }

        return $lower === $value;
    }
}