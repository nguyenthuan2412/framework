<?php

namespace Ryra\Core\Database\Model;

interface CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed;

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed;
}