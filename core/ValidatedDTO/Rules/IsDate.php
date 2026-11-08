<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\Date\Date;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsDate extends Rule
{
    public function __construct(
        protected string $format = 'Y-m-d',
        protected string $message = 'The :attribute is not valid date format.',
        protected bool $transform = false
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        $success = \is_string($value) && \date_create_from_format($this->format, $value) !== false;

        if ($success && $this->transform) {
            $value = Date::createFromFormat($this->format, $value);
        }

        return $success;
    }
}