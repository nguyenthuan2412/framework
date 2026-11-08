<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\Collection\Arr;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsGreaterThan extends Rule
{
    public function __construct(
        protected string $field,
        protected string $message = 'The :attribute must be greater than :field.'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        $compareValue = Arr::get($values, $this->field);

        if (\is_null($value) || \is_null($compareValue)) {
            return false;
        }

        if (\is_numeric($value) && \is_numeric($compareValue)) {
            return $value > $compareValue;
        }

        if (\is_array($value) && \is_array($compareValue)) {
            return \count($value) > \count($compareValue);
        }

        if (\is_string($value) && \is_string($compareValue)) {
            return \mb_strlen($value) > \mb_strlen($compareValue);
        }

        return false;
    }
}
