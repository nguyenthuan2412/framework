<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsStartsWith extends Rule
{
    public function __construct(
        protected string|array $prefix,
        protected bool $caseSensitive = true,
        protected string $message = 'The :attribute must start with :prefix.'
    ) {
        if (\is_string($this->prefix)) {
            $this->prefix = [$this->prefix];
        }
    }

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (!\is_string($value)) {
            return false;
        }

        foreach ($this->prefix as $prefix) {
            if ($this->caseSensitive) {
                if (\str_starts_with($value, $prefix)) {
                    return true;
                }
            } else {
                if (\str_starts_with(\strtolower($value), \strtolower($prefix))) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function renderPrefixText(): string
    {
        return \implode(', ', $this->prefix);
    }
}
