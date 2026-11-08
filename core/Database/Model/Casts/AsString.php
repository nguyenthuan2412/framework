<?php

namespace Ryra\Core\Database\Model\Casts;

use Ryra\Core\Database\Model\CastsAttributes;
use Ryra\Core\Database\Model\Model;

class AsString implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return (string) $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return (string) $value;
    }
}