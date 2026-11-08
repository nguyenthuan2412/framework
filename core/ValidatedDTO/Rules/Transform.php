<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Transform extends Rule
{
    protected string $message = '';

    public function __construct(protected string $method) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        $schema = $this->getSchema();
        $cloneValue = $value;
        $value = $schema::{$this->method}($cloneValue, $values, $property);
        return true;
    }
}
