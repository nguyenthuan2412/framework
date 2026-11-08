<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Rule;
use Ryra\Core\ValidatedDTO\Property;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsInteger extends Rule
{
    public function __construct(
        protected string $message = 'The :attribute must be integer',
        protected bool $transform = false
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        $success = \filter_var($value, FILTER_VALIDATE_INT) !== false;

        if ($success && $this->transform) {
            $value = (int) $value;
        }

        return $success;
    }
}
