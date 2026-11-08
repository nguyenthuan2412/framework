<?php

namespace Ryra\Core\Excel\Concerns;

interface FromArray
{
    /**
     * Return the data to export as a plain PHP array.
     */
    public function array(): array;
}
