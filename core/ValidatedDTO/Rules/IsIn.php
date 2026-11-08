<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsIn extends Rule
{
    public function __construct(
        protected array $allowedValues,
        protected bool $strict = false,
        protected string $message = 'The :attribute is not allowed :allowedValues'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        return \in_array($value, $this->allowedValues, $this->strict);
    }

    protected function renderAllowedValuesText(): string
    {
        return \implode(',', $this->allowedValues);
    }
}