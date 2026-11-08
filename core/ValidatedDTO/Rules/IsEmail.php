<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsEmail extends Rule
{
    protected string $pattern;

    public function __construct(
        protected bool $precise = false,
        protected string $message = 'The :attribute is not valid email.'
    ) {
        if ($precise) {
            $this->pattern = "/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/";
        } else {
            $this->pattern = "/^\S+@\S+\.\S+$/";
        }
    }

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        return \is_string($value) && \preg_match($this->pattern, $value);
    }
}