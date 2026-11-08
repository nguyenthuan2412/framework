<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Min extends Rule
{
    public function __construct(
        protected float $min,
        protected string $message = 'The :attribute minimum is :min'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (\is_null($value)) {
            return 0 >= $this->min;
        }

        if (\is_numeric($value) && !(\substr($value, 0, 1) === '0')) {
            return $value >= $this->min;
        }

        if (\is_array($value)) {
            return \count($value) >= $this->min;
        }

        return \mb_strlen($value) >= $this->min;
    }
}
