<?php

namespace Ryra\Core\Excel\Events;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

class BeforeWriting
{
    public function __construct(
        public readonly object $exportable,
        public readonly Spreadsheet $spreadsheet
    ) {
    }
}
