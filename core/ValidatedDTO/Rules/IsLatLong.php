<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsLatLong extends Rule
{
    // Accepts: "10.762622,106.660172" or "10.762622, 106.660172"
    private const PATTERN = '/^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?),\s*[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/';

    public function __construct(
        protected string $message = 'The :attribute must be a valid latitude,longitude string.'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        return \is_string($value) && (bool) \preg_match(self::PATTERN, $value);
    }
}
