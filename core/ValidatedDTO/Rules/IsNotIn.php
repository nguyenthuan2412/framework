<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsNotIn extends Rule
{
    public function __construct(
        protected array $disallowedValues,
        protected bool $strict = false,
        protected string $message = 'The :attribute is not allowing :disallowedValues'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        return !\in_array($value, $this->disallowedValues, $this->strict);
    }

    protected function renderDisallowedValuesText(): string
    {
        return \implode(',', $this->disallowedValues);
    }
}