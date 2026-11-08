<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Rule;
use Ryra\Core\ValidatedDTO\Property;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsUppercase extends Rule
{
    public function __construct(
        protected bool $transform = false,
        protected string $message = 'The :attribute must be uppercase.'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (!\is_string($value)) return false;

        $upper = \mb_strtoupper($value, \mb_detect_encoding($value));

        if ($this->transform) {
            $value = $upper;
            return true;
        }

        return $upper === $value;
    }
}