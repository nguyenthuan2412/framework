<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsLongitude extends Rule
{
    public function __construct(
        protected string $message = 'The :attribute must be a valid longitude (-180 to 180).'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        return \is_numeric($value) && $value >= -180 && $value <= 180;
    }
}
