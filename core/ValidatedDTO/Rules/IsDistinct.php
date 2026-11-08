<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsDistinct extends Rule
{
    public function __construct(
        protected bool $strict = false,
        protected bool $ignoreCase = false,
        protected string $message = 'The :attribute field has a duplicate value.'
    ) {}

    public function check(mixed &$list, array &$values, Property $property): bool
    {
        if ($this->ignoreCase) {
            $list = \array_map('strtolower', $list);
        }

        if (!$this->strict) {
            return \count($list) === \count(\array_flip($list));
        }

        foreach ($list as $key1 => $item1) {
            foreach ($list as $key2 => $item2) {
                if ($key1 !== $key2 && $item1 === $item2) {
                    return false;
                }
            }
        }
        return true;
    }
}