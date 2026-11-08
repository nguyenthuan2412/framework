<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsAlpha extends Rule
{
    public function __construct(protected string $message = 'The :attribute only allows alphabet characters.') {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        return \is_string($value) && \preg_match('/^[\pL\pM]+$/u', $value);
    }
}