<?php

namespace Ryra\Core\Excel\Concerns;

interface WithColumnFormatting
{
    /**
     * Return an associative array of column => PhpSpreadsheet number-format code.
     *
     * Example:
     *   use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
     *
     *   return [
     *       'B' => NumberFormat::FORMAT_DATE_DDMMYYYY,
     *       'C' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
     *       'D' => '@', // Text
     *   ];
     *
     * @return array<string, string>
     */
    public function columnFormats(): array;
}
