<?php

namespace Ryra\Core\Excel\Concerns;

interface WithMapping
{
    /**
     * Transform a single row before it is written (export) or processed (import).
     *
     * For exports: $row is the model/array item; return the mapped array of cell values.
     * For imports: $row is the raw array of cell values; return the transformed array.
     */
    public function map(mixed $row): array;
}
