<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsAccepted extends Rule
{
    public function __construct(protected string $message = 'The :attribute must be accepted.') {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        $acceptables = ['yes', 'on', '1', 1, true, 'true'];
        return \in_array($value, $acceptables, true);
    }
}