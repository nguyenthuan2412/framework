<?php

namespace Ryra\Core\Excel\Sheets;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Thin wrapper around a PhpSpreadsheet Worksheet.
 * Passed to BeforeSheet / AfterSheet events and WithStyles callbacks.
 */
class Sheet
{
    private int $currentRow = 1;

    public function __construct(
        private readonly Worksheet $worksheet,
        private readonly object $exportable
    ) {
    }

    /**
     * Access the underlying PhpSpreadsheet Worksheet directly.
     */
    public function getDelegate(): Worksheet
    {
        return $this->worksheet;
    }

    public function getExportable(): object
    {
        return $this->exportable;
    }

    public function setCurrentRow(int $row): void
    {
        $this->currentRow = $row;
    }

    public function getCurrentRow(): int
    {
        return $this->currentRow;
    }

    /**
     * Proxy all unknown calls to the underlying Worksheet.
     */
    public function __call(string $method, array $args): mixed
    {
        return $this->worksheet->$method(...$args);
    }

    /**
     * Proxy property reads to the underlying Worksheet.
     */
    public function __get(string $name): mixed
    {
        return $this->worksheet->$name;
    }
}
