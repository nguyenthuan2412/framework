<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\Collection\Arr;
use Ryra\Core\ValidatedDTO\Property;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsRequiredWhen extends IsRequired
{
    public function __construct(
        protected string|\Closure $field,
        protected array $values = [],
        protected string $message = 'The :attribute is required'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (
            \is_string($this->field)
            && \in_array(Arr::get($values, $this->field), $this->values)
        ) {
            return parent::check($value, $values, $property);
        }

        if (\is_callable($callback = $this->field) && $callback()) {
            return parent::check($value, $values, $property);
        }

        return true;
    }
}
