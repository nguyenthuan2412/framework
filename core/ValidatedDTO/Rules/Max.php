<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Max extends Rule
{
    public function __construct(
        protected float $max,
        protected string $message = 'The :attribute maximum is :max'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (\is_null($value)) {
            return 0 <= $this->max;
        }

        $isLeadingZeroInt = \is_string($value) && \ctype_digit($value) && \strlen($value) > 1 && $value[0] === '0';
        if (\is_numeric($value) && !$isLeadingZeroInt) {
            return $value <= $this->max;
        }

        if (\is_array($value)) {
            return \count($value) <= $this->max;
        }

        return \mb_strlen($value) <= $this->max;
    }
}
