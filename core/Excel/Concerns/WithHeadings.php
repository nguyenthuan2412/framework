<?php

namespace Ryra\Core\Excel\Concerns;

interface WithHeadings
{
    /**
     * Return an array of column headings to write as the first row.
     *
     * @return string[]
     */
    public function headings(): array;
}
