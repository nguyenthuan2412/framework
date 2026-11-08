<?php

namespace Ryra\Core\Database\Model\Casts;

use Ryra\Core\Database\Model\CastsAttributes;
use Ryra\Core\Database\Model\Model;
use Ryra\Core\Date\Date;

class AsDatetime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return Date::parse($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return Date::parse($value);
    }
}