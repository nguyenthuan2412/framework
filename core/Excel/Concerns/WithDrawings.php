<?php

namespace Ryra\Core\Excel\Concerns;

use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

interface WithDrawings
{
    /**
     * Return one or multiple Drawing objects to embed into the sheet.
     *
     * @return Drawing|Drawing[]
     */
    public function drawings(): Drawing|array;
}
