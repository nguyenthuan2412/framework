<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsOptional extends Rule
{
    protected string $message = '';

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        return true;
    }
}
