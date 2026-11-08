<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsDateString extends Rule
{
    // Regex ISO 8601: date, datetime, với timezone offset hoặc Z
    private const PATTERN = '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})?)?$/';

    public function __construct(
        protected string $message = 'The :attribute must be a valid ISO 8601 date string.'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (!\is_string($value)) {
            return false;
        }

        if (!\preg_match(self::PATTERN, $value)) {
            return false;
        }

        return \strtotime($value) !== false;
    }
}
