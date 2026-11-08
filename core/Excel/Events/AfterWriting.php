<?php

namespace Ryra\Core\Excel\Events;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

class AfterWriting
{
    public function __construct(
        public readonly object $exportable,
        public readonly Spreadsheet $spreadsheet
    ) {
    }
}
