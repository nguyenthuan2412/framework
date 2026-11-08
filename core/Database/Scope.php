<?php

namespace Ryra\Core\Database;

use Ryra\Core\Database\Model\Builder;
use Ryra\Core\Database\Model\Model;

interface Scope
{
    public function apply(Builder $builder, Model $model): void;
}