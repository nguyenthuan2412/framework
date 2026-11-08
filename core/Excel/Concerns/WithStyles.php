<?php

namespace Ryra\Core\Excel\Concerns;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

interface WithStyles
{
    /**
     * Apply styles to the worksheet.
     *
     * Return an associative array of [ coordinate => styleArray ] to apply styles
     * to specific cells/ranges, or manipulate $sheet directly and return void.
     *
     * Example:
     *   public function styles(Worksheet $sheet): array
     *   {
     *       return [
     *           1 => ['font' => ['bold' => true]],          // row 1
     *           'A'  => ['font' => ['italic' => true]],     // column A
     *           'B2' => ['fill' => ['fillType' => 'solid']], // cell B2
     *       ];
     *   }
     */
    public function styles(Worksheet $sheet): array|void;
}
