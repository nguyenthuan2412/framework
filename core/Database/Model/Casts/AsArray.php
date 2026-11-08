<?php

namespace Ryra\Core\Database\Model\Casts;

use Ryra\Core\Database\Model\CastsAttributes;
use Ryra\Core\Database\Model\Model;

class AsArray implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_string($value) && json_validate($value)) {
            return json_decode($value, true);
        }

        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return json_encode($value);
    }
}