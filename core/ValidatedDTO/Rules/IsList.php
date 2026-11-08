<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;
use Attribute;
use Ryra\Core\ValidatedDTO\ValidatedDTO;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsList extends Rule
{
    public function __construct(
        public readonly ?string $ruleOrSchema = null,
        protected string $message = 'The :attribute must be list'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        return \is_array($value) && \array_is_list($value);
    }
}