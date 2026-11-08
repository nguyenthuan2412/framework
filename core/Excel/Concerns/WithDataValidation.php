<?php

namespace Ryra\Core\Excel\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

interface WithDataValidation
{
    /**
     * Return the list of cell addresses that should receive data validation.
     *
     * @return string[]
     */
    public function dataValidationCells(): array;

    /**
     * Build and return the DataValidation object for a given cell address.
     */
    public function dataValidation(string $cell): DataValidation;
}
