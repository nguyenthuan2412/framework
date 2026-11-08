<?php

namespace Ryra\Core\Excel\Concerns;

use Ryra\Core\Excel\Validators\Failure;

interface SkipsOnFailure
{
    /**
     * Called when one or more validation failures occur on a row.
     * The row is skipped instead of stopping the import.
     */
    public function onFailure(Failure ...$failures): void;
}
