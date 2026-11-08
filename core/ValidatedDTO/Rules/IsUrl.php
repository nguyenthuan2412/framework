<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;
use Ryra\Core\Support\Str;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsUrl extends Rule
{
    public function __construct(protected string $message = 'The :attribute is not valid url') {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        return Str::isUrl($value);
    }
}