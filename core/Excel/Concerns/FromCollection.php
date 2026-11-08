<?php

namespace Ryra\Core\Excel\Concerns;

interface FromCollection
{
    /**
     * Return the data to export as an iterable (array, Collection, etc.).
     */
    public function collection(): iterable;
}
