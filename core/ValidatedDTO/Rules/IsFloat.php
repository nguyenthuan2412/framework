<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Rule;
use Ryra\Core\ValidatedDTO\Property;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsFloat extends Rule
{
    public function __construct(
        protected string $message = 'The :attribute must be float.',
        protected bool $transform = false
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        $success = \filter_var($value, FILTER_VALIDATE_FLOAT) !== false;

        if ($success && $this->transform) {
            $value = (float) $value;
        }

        return $success;
    }
}