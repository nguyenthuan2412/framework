<?php

namespace Ryra\Core\Database\Model\Relations;

use Ryra\Core\Database\Model\Builder;
use Ryra\Core\Database\Model\Model;

class HasOne extends Relation
{
    public function __construct(
        protected Builder $builder,
        protected Model $relatedModel,
        protected string $foreignKey,
        protected string $primaryKey
    )
    {
    }
    
    public function getResults()
    {
        return $this->builder
            ->where($this->foreignKey, $this->relatedModel->{$this->primaryKey})
            ->first();
    }

    public function create(array $fields): Model|false
    {
        $fields[$this->foreignKey] = $this->relatedModel->{$this->primaryKey};
        return $this->builder->create($fields);
    }
}