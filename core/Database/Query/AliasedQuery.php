<?php

namespace Ryra\Core\Database\Query;

class AliasedQuery extends QueryRaw
{
    protected ?string $as = null;

    public function as(string $as): static
    {
        $this->as = $as;
        return $this;
    }

    public function toSql(): string
    {
        if (is_null($this->as)) {
            return $this->sql;
        }

        return $this->sql . ' as ' . $this->as;
    }
}