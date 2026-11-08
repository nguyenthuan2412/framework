<?php

namespace Ryra\Core\Excel\Concerns;

interface SkipsUnknownSheets
{
    /**
     * Called when a sheet name/index in the file has no corresponding handler in sheets().
     * Allows graceful handling instead of throwing SheetNotFoundException.
     */
    public function onUnknownSheet(string|int $sheetName): void;
}
