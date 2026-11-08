<?php

namespace Ryra\Core\Database\Query;

class JoinClause extends Builder
{
    public function on(string $first, string $second, string $condition = '='): static
    {
        return $this->whereColumn($first, $second, $condition);
    }

    public function orOn(string $first, string $second, string $condition = '='): static
    {
        return $this->whereColumn($first, $second, $condition, 'or');
    }

    public function getBindings(): array
    {
        if (is_string($this->table)) {
            return $this->bindings['condition'];
        }

        return array_merge(
            $this->table->getBindings(),
            $this->bindings['condition']
        );
    }

    protected function buildCondition(array $conditions = [], string $prefix = 'where '): string
    {
        return parent::buildCondition($conditions, 'on ');
    }

    public function toSql(): string
    {
        if ($this->wrap) {
            return "{$this->getTable()} as `{$this->getPrefix()}{$this->wrap}` {$this->buildCondition()}";
        }
        return "{$this->getTable()} {$this->buildCondition()}";
    }
}