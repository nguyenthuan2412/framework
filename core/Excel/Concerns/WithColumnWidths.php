<?php

namespace Ryra\Core\Excel\Concerns;

interface WithColumnWidths
{
    /**
     * Return an associative array of column => width (in characters).
     *
     * Example:
     *   return ['A' => 20, 'B' => 40, 'C' => 15];
     */
    public function columnWidths(): array;
}
