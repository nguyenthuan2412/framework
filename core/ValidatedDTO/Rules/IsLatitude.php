<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsLatitude extends Rule
{
    public function __construct(
        protected string $message = 'The :attribute must be a valid latitude (-90 to 90).'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        return \is_numeric($value) && $value >= -90 && $value <= 90;
    }
}
