<?php

namespace Ryra\Core\Database\Model\Relations;

use Ryra\Core\Database\Model\Builder;
use Ryra\Core\Database\Model\Model;

class BelongsTo extends Relation
{
    public function __construct(
        protected Builder $builder,
        protected Model $relatedModel,
        protected string $foreignKey,
        protected string $primaryKey
    )
    {
    }
}