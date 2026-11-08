<?php

namespace Ryra\Core\Excel\Concerns;

use PhpOffice\PhpSpreadsheet\Style\Style;

interface WithDefaultStyles
{
    /**
     * Apply default styles to the entire spreadsheet.
     *
     * Manipulate $defaultStyle directly, or return a style array to apply via applyFromArray().
     *
     * @return array|void
     */
    public function defaultStyles(Style $defaultStyle): array|void;
}
