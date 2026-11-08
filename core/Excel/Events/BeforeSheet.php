<?php

namespace Ryra\Core\Excel\Events;

use Ryra\Core\Excel\Sheets\Sheet;

class BeforeSheet
{
    public function __construct(
        public readonly Sheet $sheet,
        public readonly object $exportable
    ) {
    }
}
