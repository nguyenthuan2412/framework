<?php

namespace Ryra\Core\Excel\Concerns;

interface WithMultipleSheets
{
    /**
     * Return an array of sheet exportable/importable objects.
     *
     * For exports: each item is an object that can implement any export concern.
     * For imports: each item is an object that handles one sheet (keyed by sheet name or index).
     *
     * @return object[]
     */
    public function sheets(): array;
}
