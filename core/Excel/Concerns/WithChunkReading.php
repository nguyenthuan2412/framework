<?php

namespace Ryra\Core\Excel\Concerns;

interface WithChunkReading
{
    /**
     * Number of rows to load from the file per chunk.
     *
     * Uses PhpSpreadsheet's IReadFilter to load only a window of rows at a time,
     * dramatically reducing memory usage for large files.
     */
    public function chunkSize(): int;
}
