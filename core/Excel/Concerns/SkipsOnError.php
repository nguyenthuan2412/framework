<?php

namespace Ryra\Core\Excel\Concerns;

interface SkipsOnError
{
    /**
     * Called when a Throwable is thrown while processing a row.
     * The row is skipped instead of stopping the import.
     */
    public function onError(\Throwable $e): void;
}
