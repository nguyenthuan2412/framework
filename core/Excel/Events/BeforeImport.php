<?php

namespace Ryra\Core\Excel\Events;

use PhpOffice\PhpSpreadsheet\Reader\IReader;

class BeforeImport
{
    public function __construct(
        public readonly object $importable,
        public readonly IReader $reader
    ) {
    }
}
