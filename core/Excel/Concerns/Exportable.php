<?php

namespace Ryra\Core\Excel\Concerns;

use Ryra\Core\App;
use Ryra\Core\Http\Response;

trait Exportable
{
    /**
     * Download the export as an HTTP response.
     *
     * @param string $fileName  e.g. 'users.xlsx'
     * @param string $writerType  'Xlsx' | 'Csv' | 'Ods' | 'Xls' | 'Html'
     */
    public function download(string $fileName, string $writerType = 'Xlsx', array $headers = []): Response
    {
        return App::getInstance()->get('excel')->download($this, $fileName, $writerType, $headers);
    }

    /**
     * Store the export to disk.
     *
     * @param string      $filePath  Path on the given disk (or absolute local path).
     * @param string|null $disk      Filesystem disk name; null = local filesystem.
     * @param string      $writerType
     */
    public function store(string $filePath, ?string $disk = null, string $writerType = 'Xlsx'): bool
    {
        return App::getInstance()->get('excel')->store($this, $filePath, $disk, $writerType);
    }

    /**
     * Return the raw file contents as a string.
     */
    public function raw(string $writerType = 'Xlsx'): string
    {
        return App::getInstance()->get('excel')->raw($this, $writerType);
    }
}
