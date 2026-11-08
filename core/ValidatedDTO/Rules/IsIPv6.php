<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsIPv6 extends Rule
{
    public function __construct(protected string $message = 'The :attribute is not valid IPv6 Address') {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        return \filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }
}
