<?php

namespace Ryra\Core\Excel\Events;

use PhpOffice\PhpSpreadsheet\Reader\IReader;

class AfterImport
{
    public function __construct(
        public readonly object $importable,
        public readonly IReader $reader
    ) {
    }
}
