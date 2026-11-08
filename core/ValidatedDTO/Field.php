<?php

namespace Ryra\Core\ValidatedDTO;

class Field
{
    public function __construct(protected string $field) {}

    public function __toString()
    {
        return $this->field;
    }
}