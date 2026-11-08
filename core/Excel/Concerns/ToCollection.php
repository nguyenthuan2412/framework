<?php

namespace Ryra\Core\Excel\Concerns;

interface ToCollection
{
    /**
     * Receive all imported rows as an iterable collection.
     * Called once after all rows have been read.
     */
    public function collection(iterable $rows): void;
}
