<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;
use Attribute;
use Ryra\Core\BaseDTO\BaseDTO;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsMap extends Rule
{
    public function __construct(
        public ?string $dtoClass = null,
        protected string $message = 'The :attribute must be associative array.'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if ($value instanceof BaseDTO) {
            return true;
        }
        return \is_array($value) && !\array_is_list($value);
    }
}
