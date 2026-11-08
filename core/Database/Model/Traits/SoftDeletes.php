<?php

namespace Ryra\Core\Database\Model\Traits;

use Ryra\Core\Database\Attributes\Scope;
use Ryra\Core\Database\Model\Builder;

trait SoftDeletes
{
    protected function onlyTrashed()
    {

    }

    protected function withTrashed()
    {

    }

    protected function restore(): void
    {
    }
}