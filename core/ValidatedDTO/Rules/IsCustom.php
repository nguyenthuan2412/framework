<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsCustom extends Rule
{
    public function __construct(
        protected string $method,
        protected string $message
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        $schema = $this->getSchema();
        return $schema::{$this->method}($value, $values, $property);
    }
}