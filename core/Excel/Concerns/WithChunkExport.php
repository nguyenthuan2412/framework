<?php

namespace Ryra\Core\Excel\Concerns;

interface WithChunkExport
{
    /**
     * Number of rows to write per chunk.
     * The writer will flush the chunk buffer and run gc_collect_cycles() after each chunk,
     * keeping peak memory usage low for very large exports.
     *
     * Combine with FromQuery to paginate the source query automatically.
     */
    public function chunkSize(): int;
}
