<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\Collection\Arr;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Equals extends Rule
{
    public function __construct(
        protected string $field,
        protected bool $strict = false,
        protected string $message = 'The :attribute must be same with :field.'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        return $this->strict
            ? $value === Arr::get($values, $this->field)
            : $value == Arr::get($values, $this->field);
    }
}
