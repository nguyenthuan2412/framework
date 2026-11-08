<?php

namespace Ryra\Core\Excel\Concerns;

interface ToArray
{
    /**
     * Receive all imported rows as a plain PHP array.
     * Called once after all rows have been read.
     */
    public function array(array $array): void;
}
