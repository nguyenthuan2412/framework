<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Rule;
use Ryra\Core\ValidatedDTO\Property;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsBoolean extends Rule
{
    public function __construct(
        protected string $message = 'The :attribute must be boolean.',
        protected bool $transform = false
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (\is_null($value)) {
            return false;
        }

        $success = $value == 1 || $value == 0;

        if ($success && $this->transform) {
            $value = (bool) $value;
        }

        return $success;
    }
}