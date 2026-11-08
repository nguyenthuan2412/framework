<?php

namespace Ryra\Core\Excel\Readers;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * A PhpSpreadsheet read filter that loads only a window of rows from the file.
 * Used by ImportReader when WithChunkReading is implemented.
 *
 * This avoids loading the entire spreadsheet into memory for large files.
 */
class ChunkReadFilter implements IReadFilter
{
    public function __construct(
        private readonly int $startRow,
        private readonly int $endRow
    ) {
    }

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        return $row >= $this->startRow && $row <= $this->endRow;
    }
}
