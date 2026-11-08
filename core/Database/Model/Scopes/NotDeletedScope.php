<?php

namespace Ryra\Core\Database\Model\Scopes;

use Ryra\Core\Database\Model\Builder;
use Ryra\Core\Database\Model\Model;
use Ryra\Core\Database\Scope;

class NotDeletedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNull('deleted_at');
    }
}
