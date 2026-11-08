<?php

namespace Ryra\Core\Excel\Concerns;

interface WithCustomStartCell
{
    /**
     * Return the cell address where writing should begin (e.g. 'B3').
     * Defaults to 'A1' if not implemented.
     */
    public function startCell(): string;
}
