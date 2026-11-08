<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;
use libphonenumber\PhoneNumberUtil;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsPhone extends Rule
{
    public function __construct(
        protected ?string $region = null,
        protected string $message = 'The :attribute is invalid.'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (empty($value)) {
            return false;
        }

        $phoneUtil = PhoneNumberUtil::getInstance();
        return $phoneUtil->isValidNumber($phoneUtil->parse($value, $this->region));
    }
}