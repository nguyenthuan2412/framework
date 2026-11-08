<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsNotRegex extends Rule
{
    public function __construct(
        protected string $pattern,
        protected string $message = 'The :attribute is not valid format'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        return !(bool) \preg_match($this->pattern, $value);
    }
}