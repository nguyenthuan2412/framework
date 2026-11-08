<?php

namespace Ryra\Core\Excel\Concerns;

use Ryra\Core\App;

trait Importable
{
    /**
     * Run the import from a file path (or disk path).
     *
     * @param string      $filePath    Local path or path on the given disk.
     * @param string|null $disk        Filesystem disk name; null = local filesystem.
     * @param string|null $readerType  'Xlsx' | 'Csv' | 'Ods' | 'Xls' | null (auto-detect).
     */
    public function import(string $filePath, ?string $disk = null, ?string $readerType = null): static
    {
        App::getInstance()->get('excel')->import($this, $filePath, $disk, $readerType);
        return $this;
    }

    /**
     * Read the file and return all rows as a plain PHP array.
     */
    public function toArray(string $filePath, ?string $disk = null, ?string $readerType = null): array
    {
        return App::getInstance()->get('excel')->toArray($this, $filePath, $disk, $readerType);
    }

    /**
     * Read the file and return all rows as an iterable collection (array of arrays).
     */
    public function toCollection(string $filePath, ?string $disk = null, ?string $readerType = null): array
    {
        return App::getInstance()->get('excel')->toCollection($this, $filePath, $disk, $readerType);
    }
}
