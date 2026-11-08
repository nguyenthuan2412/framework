<?php

namespace Ryra\Core\Excel\Events;

use PhpOffice\PhpSpreadsheet\Writer\IWriter;

class AfterExport
{
    public function __construct(
        public readonly object $exportable,
        public readonly IWriter $writer
    ) {
    }
}
