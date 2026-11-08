<?php

namespace Ryra\Core\Excel\Concerns;

interface WithStartRow
{
    /**
     * Return the 1-based row number where data rows begin.
     * Useful when there are title/summary rows above the actual data.
     */
    public function startRow(): int;
}
