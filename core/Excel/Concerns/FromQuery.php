<?php

namespace Ryra\Core\Excel\Concerns;

interface FromQuery
{
    /**
     * Return a query builder instance. The writer will call ->get() internally.
     * Combine with WithChunkExport to process results in chunks.
     */
    public function query(): mixed;
}
