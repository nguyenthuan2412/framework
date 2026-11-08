<?php

namespace Ryra\Core\Excel\Concerns;

interface WithHeadingRow
{
    /**
     * Return the 1-based row number that contains the column headings.
     * Rows from this row onward will have their columns keyed by the heading values.
     * Defaults to row 1 if not implemented.
     */
    public function headingRow(): int;
}
