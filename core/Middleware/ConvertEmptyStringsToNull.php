<?php

namespace Ryra\Core\Middleware;

class ConvertEmptyStringsToNull extends TransformsRequest
{
    /**
     * Transform the given value.
     */
    protected function transform(string $key, mixed $value): mixed
    {
        return is_string($value) && $value === '' ? null : $value;
    }
}