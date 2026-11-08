<?php

namespace Ryra\Core\Database\Query\Traits;

trait BuildsQueries
{
    public function first(array $columns = []): mixed
    {
        return $this->limit(1)->get($columns)->first();
    }
}