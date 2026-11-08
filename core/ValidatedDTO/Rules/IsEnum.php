<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Rule;
use Ryra\Core\ValidatedDTO\Property;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsEnum extends Rule
{
    protected string $message = 'The selected :attribute is invalid.';

    public function __construct(
        protected string $enum,
        protected array $only = [],
        protected array $except = [],
        protected bool $transform = false
    ) {
        if (!\enum_exists($enum)) {
            throw new \InvalidArgumentException('Enum class not exists.');
        }

        foreach ($only as $item) {
            if (!$item instanceof $enum) {
                throw new \InvalidArgumentException('Item in only array must be instance of enum.');
            }
        }

        foreach ($except as $item) {
            if (!$item instanceof $enum) {
                throw new \InvalidArgumentException('Item in except array must be instance of enum.');
            }
        }
    }

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        $enum = $this->enum;

        if (!is_int($value) && !is_string($value)) {
            return false;
        }

        $instance = $enum::tryFrom($value);

        if ($instance === null) return false;

        if (!empty($this->only) && !\in_array($instance, $this->only, true)) return false;

        if (!empty($this->except) && \in_array($instance, $this->except, true)) return false;

        if ($this->transform) {
            $value = $instance;
        }

        return true;
    }
}