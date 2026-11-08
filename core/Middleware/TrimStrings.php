<?php

namespace Ryra\Core\Middleware;

use Ryra\Core\Middleware\TransformsRequest;

class TrimStrings extends TransformsRequest
{
    protected function transform(string $key, mixed $value): mixed
    {
        if (is_string($value)) {
            return preg_replace('~^[\s\x{FEFF}\x{200B}]+|[\s\x{FEFF}\x{200B}]+$~u', '', $value ?? '') ?? trim($value);
        }

        return $value;
    }
}
