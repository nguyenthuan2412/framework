<?php

namespace Ryra\Core\Excel\Readers;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Ryra\Core\Excel\Concerns\SkipsEmptyRows;
use Ryra\Core\Excel\Concerns\SkipsOnError;
use Ryra\Core\Excel\Concerns\SkipsOnFailure;
use Ryra\Core\Excel\Concerns\SkipsUnknownSheets;
use Ryra\Core\Excel\Concerns\ToArray;
use Ryra\Core\Excel\Concerns\ToCollection;
use Ryra\Core\Excel\Concerns\ToModel;
use Ryra\Core\Excel\Concerns\WithBatchInserts;
use Ryra\Core\Excel\Concerns\WithChunkReading;
use Ryra\Core\Excel\Concerns\WithCustomCsvSettings;
use Ryra\Core\Excel\Concerns\WithEvents;
use Ryra\Core\Excel\Concerns\WithHeadingRow;
use Ryra\Core\Excel\Concerns\WithMappedCells;
use Ryra\Core\Excel\Concerns\WithMapping;
use Ryra\Core\Excel\Concerns\WithMultipleSheets;
use Ryra\Core\Excel\Concerns\WithStartRow;
use Ryra\Core\Excel\Concerns\WithValidation;
use Ryra\Core\Excel\Events\AfterImport;
use Ryra\Core\Excel\Events\BeforeImport;
use Ryra\Core\Excel\Exceptions\SheetNotFoundException;
use Ryra\Core\Excel\Validators\Failure;

class ImportReader
{
    private const READER_MAP = [
        'Xlsx' => \PhpOffice\PhpSpreadsheet\Reader\Xlsx::class,
        'Csv'  => \PhpOffice\PhpSpreadsheet\Reader\Csv::class,
        'Ods'  => \PhpOffice\PhpSpreadsheet\Reader\Ods::class,
        'Xls'  => \PhpOffice\PhpSpreadsheet\Reader\Xls::class,
        'Html' => \PhpOffice\PhpSpreadsheet\Reader\Html::class,
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Run a full import (ToModel / ToCollection / ToArray destination).
     */
    public function read(object $importable, string $filePath, ?string $readerType = null): void
    {
        $reader = $this->createReader($filePath, $readerType, $importable);
        $this->fireEvent($importable, new BeforeImport($importable, $reader));

        if ($importable instanceof WithMultipleSheets) {
            $this->readMultipleSheets($reader, $filePath, $importable);
        } elseif ($importable instanceof WithChunkReading) {
            $this->readChunked($reader, $filePath, $importable);
        } else {
            $spreadsheet = $reader->load($filePath);
            $worksheet   = $spreadsheet->getActiveSheet();
            $this->processSheet($worksheet, $importable);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        $this->fireEvent($importable, new AfterImport($importable, $reader));
    }

    /**
     * Read the file and return all rows as a plain PHP array (does not trigger ToModel etc.).
     */
    public function toArray(object $importable, string $filePath, ?string $readerType = null): array
    {
        $reader      = $this->createReader($filePath, $readerType, $importable);
        $spreadsheet = $reader->load($filePath);
        $worksheet   = $spreadsheet->getActiveSheet();
        $results     = $this->collectRows($worksheet, $importable);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        return $results;
    }

    // -------------------------------------------------------------------------
    // Multiple sheets
    // -------------------------------------------------------------------------

    private function readMultipleSheets(IReader $reader, string $filePath, object $importable): void
    {
        $sheetHandlers = $importable->sheets();
        $spreadsheet   = $reader->load($filePath);

        foreach ($sheetHandlers as $key => $handler) {
            if (is_int($key)) {
                if (!$spreadsheet->sheetNameExists((string) $key) && $spreadsheet->getSheetCount() <= $key) {
                    $this->handleUnknownSheet($importable, $key);
                    continue;
                }
                $worksheet = $spreadsheet->getSheet($key) ?? null;
            } else {
                if (!$spreadsheet->sheetNameExists($key)) {
                    $this->handleUnknownSheet($importable, $key);
                    continue;
                }
                $worksheet = $spreadsheet->getSheetByName($key);
            }

            if ($worksheet === null) {
                $this->handleUnknownSheet($importable, $key);
                continue;
            }

            $this->processSheet($worksheet, $handler);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function handleUnknownSheet(object $importable, string|int $key): void
    {
        if ($importable instanceof SkipsUnknownSheets) {
            $importable->onUnknownSheet($key);
            return;
        }

        if (is_int($key)) {
            throw SheetNotFoundException::byIndex($key);
        }

        throw SheetNotFoundException::byName($key);
    }

    // -------------------------------------------------------------------------
    // Chunked reading (performance path)
    // -------------------------------------------------------------------------

    /**
     * Load and process the file in row-windows using ChunkReadFilter.
     * Peak memory stays proportional to the chunk size, not the file size.
     */
    private function readChunked(IReader $reader, string $filePath, object $importable): void
    {
        $chunkSize = $importable->chunkSize();

        // Read heading row first if needed
        $headings  = null;
        $startRow  = $this->resolveStartRow($importable);

        if ($importable instanceof WithHeadingRow) {
            $headingRow = $importable->headingRow();
            $reader->setReadFilter(new ChunkReadFilter($headingRow, $headingRow));
            $spreadsheet = $reader->load($filePath);
            $headings    = $this->buildHeadings($spreadsheet->getActiveSheet(), $headingRow);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            $startRow = max($startRow, $headingRow + 1);
        }

        // Determine total row count by loading the last chunk first
        // (cheaper than loading the whole file)
        $currentRow = $startRow;

        while (true) {
            $endRow = $currentRow + $chunkSize - 1;

            $reader->setReadFilter(new ChunkReadFilter($currentRow, $endRow));
            $spreadsheet    = $reader->load($filePath);
            $worksheet      = $spreadsheet->getActiveSheet();
            $highestDataRow = $worksheet->getHighestDataRow();

            $rowsInChunk = $this->collectRowsRange($worksheet, $importable, $currentRow, min($endRow, $highestDataRow), $headings);

            $this->dispatchRows($rowsInChunk, $importable);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $rowsInChunk);
            gc_collect_cycles();

            if ($highestDataRow <= $endRow) {
                break;
            }

            $currentRow += $chunkSize;
        }
    }

    // -------------------------------------------------------------------------
    // Sheet processing (non-chunked)
    // -------------------------------------------------------------------------

    private function processSheet(Worksheet $worksheet, object $importable): void
    {
        // Mapped cells (form-style import, non-tabular)
        if ($importable instanceof WithMappedCells) {
            $data = [];
            foreach ($importable->mapping() as $field => $cell) {
                $data[$field] = $worksheet->getCell($cell)->getFormattedValue();
            }
            $importable->model($data);
            return;
        }

        $rows = $this->collectRows($worksheet, $importable);
        $this->dispatchRows($rows, $importable);
    }

    // -------------------------------------------------------------------------
    // Row collection helpers
    // -------------------------------------------------------------------------

    private function collectRows(Worksheet $worksheet, object $importable): array
    {
        $startRow = $this->resolveStartRow($importable);
        $headings = null;

        if ($importable instanceof WithHeadingRow) {
            $headingRow = $importable->headingRow();
            $headings   = $this->buildHeadings($worksheet, $headingRow);
            $startRow   = max($startRow, $headingRow + 1);
        }

        $highestRow = $worksheet->getHighestDataRow();
        return $this->collectRowsRange($worksheet, $importable, $startRow, $highestRow, $headings);
    }

    private function collectRowsRange(
        Worksheet $worksheet,
        object $importable,
        int $startRow,
        int $endRow,
        ?array $headings
    ): array {
        $rows = [];

        for ($rowIndex = $startRow; $rowIndex <= $endRow; $rowIndex++) {
            $rawRow = $this->readRow($worksheet, $rowIndex);

            if ($importable instanceof SkipsEmptyRows && $this->isEmptyRow($rawRow)) {
                continue;
            }

            if ($headings !== null) {
                $count  = count($headings);
                $rawRow = array_combine($headings, array_pad(array_slice($rawRow, 0, $count), $count, null));
            }

            if ($importable instanceof WithMapping) {
                $rawRow = $importable->map($rawRow);
            }

            $rows[] = $rawRow;
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // Row dispatching (ToModel / ToCollection / ToArray)
    // -------------------------------------------------------------------------

    private function dispatchRows(array $rows, object $importable): void
    {
        if ($importable instanceof ToModel) {
            $this->dispatchToModel($rows, $importable);
        } elseif ($importable instanceof ToCollection) {
            $importable->collection($rows);
        } elseif ($importable instanceof ToArray) {
            $importable->array($rows);
        }
    }

    private function dispatchToModel(array $rows, object $importable): void
    {
        $batchSize = ($importable instanceof WithBatchInserts) ? $importable->batchSize() : 0;
        $batch     = [];

        foreach ($rows as $rowIndex => $row) {
            // Validate row
            if ($importable instanceof WithValidation) {
                $failures = $this->validateRow($row, $rowIndex, $importable);
                if (!empty($failures)) {
                    if ($importable instanceof SkipsOnFailure) {
                        $importable->onFailure(...$failures);
                        continue;
                    }
                    throw new \Ryra\Core\Excel\Exceptions\RowSkippedException($failures);
                }
            }

            try {
                $model = $importable->model($row);
                if ($model !== null) {
                    $batch[] = $model;
                }
            } catch (\Throwable $e) {
                if ($importable instanceof SkipsOnError) {
                    $importable->onError($e);
                    continue;
                }
                throw $e;
            }

            if ($batchSize > 0 && count($batch) >= $batchSize) {
                $this->saveBatch($batch);
                $batch = [];
                gc_collect_cycles();
            }
        }

        // Flush remaining
        if (!empty($batch)) {
            $this->saveBatch($batch);
        }
    }

    private function saveBatch(array $models): void
    {
        foreach ($models as $model) {
            if (is_object($model) && method_exists($model, 'save')) {
                $model->save();
            }
        }
    }

    // -------------------------------------------------------------------------
    // Row validation
    // -------------------------------------------------------------------------

    /** @return Failure[] */
    private function validateRow(array $row, int $rowIndex, WithValidation $importable): array
    {
        $rules    = $importable->rules($row);
        $messages = method_exists($importable, 'customValidationMessages')
            ? $importable->customValidationMessages()
            : [];

        $failures = [];
        foreach ($rules as $field => $rule) {
            $value  = $row[$field] ?? null;
            $errors = $this->applyRule($field, $value, $rule, $messages);
            foreach ($errors as $error) {
                $failures[] = new Failure($rowIndex + 1, $field, [$error], $row);
            }
        }

        return $failures;
    }

    private function applyRule(string $field, mixed $value, string|array $rule, array $messages): array
    {
        $rules  = is_array($rule) ? $rule : explode('|', $rule);
        $errors = [];

        foreach ($rules as $r) {
            [$ruleName, $ruleParam] = array_pad(explode(':', $r, 2), 2, null);

            $error = match ($ruleName) {
                'required' => ($value === null || $value === '') ? ($messages["$field.required"] ?? "The $field field is required.") : null,
                'numeric'  => ($value !== null && $value !== '' && !is_numeric($value)) ? ($messages["$field.numeric"] ?? "The $field must be numeric.") : null,
                'integer'  => ($value !== null && $value !== '' && !ctype_digit((string) $value)) ? ($messages["$field.integer"] ?? "The $field must be an integer.") : null,
                'email'    => ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) ? ($messages["$field.email"] ?? "The $field must be a valid email address.") : null,
                'max'      => ($value !== null && $ruleParam !== null && mb_strlen((string) $value) > (int) $ruleParam) ? ($messages["$field.max"] ?? "The $field may not be greater than $ruleParam characters.") : null,
                'min'      => ($value !== null && $ruleParam !== null && mb_strlen((string) $value) < (int) $ruleParam) ? ($messages["$field.min"] ?? "The $field must be at least $ruleParam characters.") : null,
                'string'   => ($value !== null && !is_string($value)) ? ($messages["$field.string"] ?? "The $field must be a string.") : null,
                default    => null,
            };

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    // -------------------------------------------------------------------------
    // Row / heading readers
    // -------------------------------------------------------------------------

    private function readRow(Worksheet $worksheet, int $rowIndex): array
    {
        $highestCol = $worksheet->getHighestDataColumn($rowIndex);
        $highestIdx = Coordinate::columnIndexFromString($highestCol);

        $data = [];
        for ($col = 1; $col <= $highestIdx; $col++) {
            $data[] = $worksheet->getCell([$col, $rowIndex])->getFormattedValue();
        }

        return $data;
    }

    private function buildHeadings(Worksheet $worksheet, int $rowIndex): array
    {
        return array_map(
            fn ($h) => $this->normalizeHeading($h),
            $this->readRow($worksheet, $rowIndex)
        );
    }

    private function normalizeHeading(mixed $heading): string
    {
        $heading = trim((string) ($heading ?? ''));
        $heading = mb_strtolower($heading);
        $heading = preg_replace('/\s+/u', '_', $heading);
        $heading = preg_replace('/[^\w]/u', '', $heading);
        return $heading;
    }

    private function isEmptyRow(array $row): bool
    {
        return empty(array_filter($row, fn ($v) => $v !== null && $v !== ''));
    }

    private function resolveStartRow(object $importable): int
    {
        return ($importable instanceof WithStartRow) ? $importable->startRow() : 1;
    }

    // -------------------------------------------------------------------------
    // Reader factory
    // -------------------------------------------------------------------------

    private function createReader(string $filePath, ?string $readerType, object $importable): IReader
    {
        if ($readerType !== null && isset(self::READER_MAP[$readerType])) {
            $reader = new (self::READER_MAP[$readerType])();
        } else {
            $reader = IOFactory::createReaderForFile($filePath);
        }

        // Read-only mode for maximum performance
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        // CSV settings
        if ($reader instanceof CsvReader && $importable instanceof WithCustomCsvSettings) {
            $settings = $importable->getCsvSettings();
            isset($settings['delimiter'])        && $reader->setDelimiter($settings['delimiter']);
            isset($settings['enclosure'])        && $reader->setEnclosure($settings['enclosure']);
            isset($settings['escape_character']) && $reader->setEscapeCharacter($settings['escape_character']);
            isset($settings['input_encoding'])   && $reader->setInputEncoding($settings['input_encoding']);
            isset($settings['contiguous'])       && $reader->setContiguous((bool) $settings['contiguous']);
        }

        return $reader;
    }

    // -------------------------------------------------------------------------
    // Event dispatching
    // -------------------------------------------------------------------------

    private function fireEvent(object $importable, object $event): void
    {
        if (!($importable instanceof WithEvents)) {
            return;
        }

        $events    = $importable->registerEvents();
        $eventClass = get_class($event);

        if (isset($events[$eventClass]) && is_callable($events[$eventClass])) {
            ($events[$eventClass])($event);
        }
    }
}
