<?php

namespace Ryra\Core\Excel;

use Ryra\Core\Excel\Files\TemporaryFile;
use Ryra\Core\Excel\Readers\ImportReader;
use Ryra\Core\Excel\Writers\ExportWriter;
use Ryra\Core\Filesystem\Facade\Filesystem;
use Ryra\Core\Http\Response;

/**
 * Main Excel manager — registered as 'excel' in the service container.
 *
 * Usage via Facade:
 *   Excel::download(new UsersExport, 'users.xlsx')
 *   Excel::store(new UsersExport, 'exports/users.xlsx', 'local')
 *   Excel::import(new UsersImport, 'uploads/users.xlsx')
 *   Excel::toArray(new UsersImport, 'uploads/users.xlsx')
 *
 * Supported writer/reader types: 'Xlsx' | 'Csv' | 'Ods' | 'Xls' | 'Html'
 */
class Excel
{
    public const XLSX = 'Xlsx';
    public const CSV  = 'Csv';
    public const ODS  = 'Ods';
    public const XLS  = 'Xls';
    public const HTML = 'Html';

    private static array $mimeTypes = [
        'Xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Csv'  => 'text/csv; charset=UTF-8',
        'Ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        'Xls'  => 'application/vnd.ms-excel',
        'Html' => 'text/html; charset=UTF-8',
    ];

    public function __construct(
        private readonly ExportWriter $writer,
        private readonly ImportReader $reader
    ) {
    }

    // =========================================================================
    // Exports
    // =========================================================================

    /**
     * Download the export as an HTTP response (triggers file download in browser).
     */
    public function download(
        object $exportable,
        string $fileName,
        string $writerType = self::XLSX,
        array $headers = []
    ): Response {
        $content  = $this->writer->export($exportable, $writerType);

        return Response::streamDownload($content, $fileName, $headers);
    }

    /**
     * Store the export to disk and return true on success.
     */
    public function store(
        object $exportable,
        string $filePath,
        ?string $disk = null,
        string $writerType = self::XLSX
    ): bool {
        $content = $this->writer->export($exportable, $writerType);

        if ($disk !== null) {
            Filesystem::driver($disk)->write($filePath, $content);
        } else {
            $dir = dirname($filePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($filePath, $content);
        }

        return true;
    }

    /**
     * Return the raw file contents as a binary string.
     */
    public function raw(object $exportable, string $writerType = self::XLSX): string
    {
        return $this->writer->export($exportable, $writerType);
    }

    /**
     * Export to a TemporaryFile and return it.
     * Caller is responsible for cleanup (or let __destruct handle it).
     */
    public function toTemporaryFile(object $exportable, string $writerType = self::XLSX): TemporaryFile
    {
        $extension = strtolower($writerType);
        $temp      = new TemporaryFile($extension);
        file_put_contents($temp->getPath(), $this->writer->export($exportable, $writerType));
        return $temp;
    }

    // =========================================================================
    // Imports
    // =========================================================================

    /**
     * Run a full import (ToModel / ToCollection / ToArray destination).
     */
    public function import(
        object $importable,
        string $filePath,
        ?string $disk = null,
        ?string $readerType = null
    ): void {
        $resolved  = $this->resolveFilePath($filePath, $disk);
        $isTempFile = $disk !== null;

        try {
            $this->reader->read($importable, $resolved, $readerType);
        } finally {
            if ($isTempFile && file_exists($resolved)) {
                @unlink($resolved);
            }
        }
    }

    /**
     * Read the file and return all rows as a plain PHP array.
     * Does NOT trigger ToModel / ToCollection / ToArray on the importable.
     */
    public function toArray(
        object $importable,
        string $filePath,
        ?string $disk = null,
        ?string $readerType = null
    ): array {
        $resolved   = $this->resolveFilePath($filePath, $disk);
        $isTempFile = $disk !== null;

        try {
            return $this->reader->toArray($importable, $resolved, $readerType);
        } finally {
            if ($isTempFile && file_exists($resolved)) {
                @unlink($resolved);
            }
        }
    }

    /**
     * Read the file and return all rows as an array of arrays.
     * Alias for toArray() — kept for API parity with Laravel Excel.
     */
    public function toCollection(
        object $importable,
        string $filePath,
        ?string $disk = null,
        ?string $readerType = null
    ): array {
        return $this->toArray($importable, $filePath, $disk, $readerType);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * If a disk is given, download the file from the disk to a local temp path.
     */
    private function resolveFilePath(string $filePath, ?string $disk): string
    {
        if ($disk === null) {
            return $filePath;
        }

        $content  = Filesystem::driver($disk)->read($filePath);
        $tempPath = tempnam(sys_get_temp_dir(), 'excel_import_') . '.' . pathinfo($filePath, PATHINFO_EXTENSION);
        file_put_contents($tempPath, $content);

        return $tempPath;
    }
}
