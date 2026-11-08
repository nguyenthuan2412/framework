<?php

namespace Ryra\Core\Excel\Validators;

class Failure
{
    public function __construct(
        private readonly int $row,
        private readonly string $attribute,
        private readonly array $errors,
        private readonly array $values = []
    ) {
    }

    public function row(): int
    {
        return $this->row;
    }

    public function attribute(): string
    {
        return $this->attribute;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function values(): array
    {
        return $this->values;
    }

    public function toArray(): array
    {
        return [
            'row'       => $this->row,
            'attribute' => $this->attribute,
            'errors'    => $this->errors,
            'values'    => $this->values,
        ];
    }
}
