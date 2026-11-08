<?php

namespace Ryra\Core\Excel\Writers;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use Ryra\Core\Excel\Concerns\FromArray;
use Ryra\Core\Excel\Concerns\FromCollection;
use Ryra\Core\Excel\Concerns\FromGenerator;
use Ryra\Core\Excel\Concerns\FromQuery;
use Ryra\Core\Excel\Concerns\ShouldAutoSize;
use Ryra\Core\Excel\Concerns\ShouldPreCalcFormulas;
use Ryra\Core\Excel\Concerns\WithBackgroundColor;
use Ryra\Core\Excel\Concerns\WithChunkExport;
use Ryra\Core\Excel\Concerns\WithColumnFormatting;
use Ryra\Core\Excel\Concerns\WithColumnWidths;
use Ryra\Core\Excel\Concerns\WithCustomCsvSettings;
use Ryra\Core\Excel\Concerns\WithCustomStartCell;
use Ryra\Core\Excel\Concerns\WithDataValidation;
use Ryra\Core\Excel\Concerns\WithDefaultStyles;
use Ryra\Core\Excel\Concerns\WithDrawings;
use Ryra\Core\Excel\Concerns\WithEvents;
use Ryra\Core\Excel\Concerns\WithHeadings;
use Ryra\Core\Excel\Concerns\WithMapping;
use Ryra\Core\Excel\Concerns\WithMultipleSheets;
use Ryra\Core\Excel\Concerns\WithProperties;
use Ryra\Core\Excel\Concerns\WithStrictNullComparison;
use Ryra\Core\Excel\Concerns\WithStyles;
use Ryra\Core\Excel\Concerns\WithTitle;
use Ryra\Core\Excel\Events\AfterExport;
use Ryra\Core\Excel\Events\AfterSheet;
use Ryra\Core\Excel\Events\AfterWriting;
use Ryra\Core\Excel\Events\BeforeExport;
use Ryra\Core\Excel\Events\BeforeSheet;
use Ryra\Core\Excel\Events\BeforeWriting;
use Ryra\Core\Excel\Sheets\Sheet;

class ExportWriter
{
    private const WRITER_MAP = [
        'Xlsx' => \PhpOffice\PhpSpreadsheet\Writer\Xlsx::class,
        'Csv'  => \PhpOffice\PhpSpreadsheet\Writer\Csv::class,
        'Ods'  => \PhpOffice\PhpSpreadsheet\Writer\Ods::class,
        'Xls'  => \PhpOffice\PhpSpreadsheet\Writer\Xls::class,
        'Html' => \PhpOffice\PhpSpreadsheet\Writer\Html::class,
    ];

    /**
     * Export the given exportable object and return the raw file contents.
     */
    public function export(object $exportable, string $writerType = 'Xlsx'): string
    {
        $spreadsheet = $this->buildSpreadsheet($exportable);
        $writer      = $this->createWriter($spreadsheet, $writerType, $exportable);

        $this->fireEvent($exportable, new BeforeExport($exportable, $writer));
        $this->fireEvent($exportable, new BeforeWriting($exportable, $spreadsheet));

        if ($exportable instanceof ShouldPreCalcFormulas) {
            $writer->setPreCalculateFormulas(true);
        } else {
            $writer->setPreCalculateFormulas(false);
        }

        $temp = fopen('php://memory', 'w+');
        $writer->save($temp);
        rewind($temp);
        $content = stream_get_contents($temp);
        fclose($temp);

        $this->fireEvent($exportable, new AfterWriting($exportable, $spreadsheet));
        $this->fireEvent($exportable, new AfterExport($exportable, $writer));

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $content ?: '';
    }

    // -------------------------------------------------------------------------
    // Spreadsheet building
    // -------------------------------------------------------------------------

    private function buildSpreadsheet(object $exportable): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        // Document properties
        if ($exportable instanceof WithProperties) {
            $props = $exportable->properties();
            $p     = $spreadsheet->getProperties();
            isset($props['creator'])        && $p->setCreator($props['creator']);
            isset($props['lastModifiedBy']) && $p->setLastModifiedBy($props['lastModifiedBy']);
            isset($props['title'])          && $p->setTitle($props['title']);
            isset($props['description'])    && $p->setDescription($props['description']);
            isset($props['subject'])        && $p->setSubject($props['subject']);
            isset($props['keywords'])       && $p->setKeywords($props['keywords']);
            isset($props['category'])       && $p->setCategory($props['category']);
            isset($props['company'])        && $p->setCompany($props['company']);
            isset($props['manager'])        && $p->setManager($props['manager']);
        }

        // Default styles
        if ($exportable instanceof WithDefaultStyles) {
            $styles = $exportable->defaultStyles($spreadsheet->getDefaultStyle());
            if (is_array($styles) && !empty($styles)) {
                $spreadsheet->getDefaultStyle()->applyFromArray($styles);
            }
        }

        // Multiple sheets
        if ($exportable instanceof WithMultipleSheets) {
            $sheets = $exportable->sheets();
            $spreadsheet->removeSheetByIndex(0);
            foreach (array_values($sheets) as $index => $sheetExportable) {
                $worksheet = new Worksheet($spreadsheet);
                $spreadsheet->addSheet($worksheet, $index);
                $this->fillSheet($worksheet, $sheetExportable);
            }
        } else {
            $this->fillSheet($spreadsheet->getActiveSheet(), $exportable);
        }

        return $spreadsheet;
    }

    private function fillSheet(Worksheet $worksheet, object $exportable): void
    {
        $sheet = new Sheet($worksheet, $exportable);

        // Sheet title
        if ($exportable instanceof WithTitle) {
            $title = mb_substr($exportable->title(), 0, 31);
            $worksheet->setTitle($title);
        }

        $this->fireEvent($exportable, new BeforeSheet($sheet, $exportable));

        // Determine start cell
        $startCell = ($exportable instanceof WithCustomStartCell) ? $exportable->startCell() : 'A1';
        [$startColumn, $startRow] = Coordinate::coordinateFromString($startCell);
        $currentRow  = (int) $startRow;
        $startColIdx = Coordinate::columnIndexFromString($startColumn);

        // Headings row
        if ($exportable instanceof WithHeadings) {
            $col = $startColIdx;
            foreach ($exportable->headings() as $heading) {
                $worksheet->getCell([$col++, $currentRow])->setValue($heading);
            }
            $sheet->setCurrentRow(++$currentRow);
        }

        // Data rows
        $currentRow = $this->writeData($worksheet, $exportable, $startColIdx, $currentRow);
        $sheet->setCurrentRow($currentRow);

        // Column widths
        if ($exportable instanceof WithColumnWidths) {
            foreach ($exportable->columnWidths() as $column => $width) {
                $worksheet->getColumnDimension($column)->setWidth((float) $width);
            }
        }

        // Auto size (applied after data so dimensions are accurate)
        if ($exportable instanceof ShouldAutoSize) {
            foreach ($worksheet->getColumnIterator() as $column) {
                $worksheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
            }
        }

        // Column number formatting
        if ($exportable instanceof WithColumnFormatting) {
            foreach ($exportable->columnFormats() as $column => $format) {
                $worksheet->getStyle($column)->getNumberFormat()->setFormatCode($format);
            }
        }

        // Custom styles
        if ($exportable instanceof WithStyles) {
            $styles = $exportable->styles($worksheet);
            if (is_array($styles)) {
                foreach ($styles as $coordinate => $styleArray) {
                    $worksheet->getStyle((string) $coordinate)->applyFromArray($styleArray);
                }
            }
        }

        // Background color (applied before other styles so styles can override)
        if ($exportable instanceof WithBackgroundColor) {
            $highest = $worksheet->getHighestRowAndColumn();
            if ($highest['row'] >= 1) {
                $range = 'A1:' . $highest['column'] . $highest['row'];
                $worksheet->getStyle($range)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($exportable->backgroundColor());
            }
        }

        // Drawings
        if ($exportable instanceof WithDrawings) {
            $drawings = $exportable->drawings();
            foreach ((array) $drawings as $drawing) {
                $drawing->setWorksheet($worksheet);
            }
        }

        // Data validation
        if ($exportable instanceof WithDataValidation) {
            foreach ($exportable->dataValidationCells() as $cell) {
                $worksheet->getCell($cell)->setDataValidation(
                    $exportable->dataValidation($cell)
                );
            }
        }

        $this->fireEvent($exportable, new AfterSheet($sheet, $exportable));
    }

    // -------------------------------------------------------------------------
    // Row writing (with chunking + GC support)
    // -------------------------------------------------------------------------

    private function writeData(Worksheet $worksheet, object $exportable, int $startColIdx, int $startRow): int
    {
        $chunkSize   = ($exportable instanceof WithChunkExport) ? $exportable->chunkSize() : 1000;
        $strictNull  = $exportable instanceof WithStrictNullComparison;

        // For FromQuery with WithChunkExport — paginate the query
        if ($exportable instanceof FromQuery && $exportable instanceof WithChunkExport) {
            return $this->writeFromQueryChunked($worksheet, $exportable, $startColIdx, $startRow, $chunkSize, $strictNull);
        }

        $data        = $this->getDataSource($exportable);
        $chunk       = [];
        $currentRow  = $startRow;

        foreach ($data as $row) {
            if ($exportable instanceof WithMapping) {
                $row = $exportable->map($row);
            }

            $row = is_array($row) ? $row : (array) $row;

            if ($strictNull) {
                $row = array_map(fn ($v) => $v ?? '', $row);
            }

            $chunk[] = $row;

            if (count($chunk) >= $chunkSize) {
                $currentRow = $this->flushRows($worksheet, $chunk, $startColIdx, $currentRow);
                $chunk      = [];
                gc_collect_cycles();
            }
        }

        if (!empty($chunk)) {
            $currentRow = $this->flushRows($worksheet, $chunk, $startColIdx, $currentRow);
        }

        return $currentRow;
    }

    private function writeFromQueryChunked(
        Worksheet $worksheet,
        object $exportable,
        int $startColIdx,
        int $startRow,
        int $chunkSize,
        bool $strictNull
    ): int {
        $query      = $exportable->query();
        $currentRow = $startRow;
        $offset     = 0;

        while (true) {
            $rows = $query->limit($chunkSize)->offset($offset)->get();
            if (empty($rows) || (is_countable($rows) && count($rows) === 0)) {
                break;
            }

            $chunk = [];
            foreach ($rows as $row) {
                if ($exportable instanceof WithMapping) {
                    $row = $exportable->map($row);
                }
                $row = is_array($row) ? $row : (array) $row;
                if ($strictNull) {
                    $row = array_map(fn ($v) => $v ?? '', $row);
                }
                $chunk[] = $row;
            }

            $currentRow = $this->flushRows($worksheet, $chunk, $startColIdx, $currentRow);
            unset($rows, $chunk);
            gc_collect_cycles();

            $offset += $chunkSize;

            // Stop if fewer rows than chunk size were returned
            if (!is_countable($rows ?? []) || count($rows ?? []) < $chunkSize) {
                break;
            }
        }

        return $currentRow;
    }

    private function flushRows(Worksheet $worksheet, array $rows, int $startColIdx, int $currentRow): int
    {
        foreach ($rows as $row) {
            $col = $startColIdx;
            foreach ($row as $value) {
                $worksheet->getCell([$col++, $currentRow])->setValue($value);
            }
            $currentRow++;
        }
        return $currentRow;
    }

    // -------------------------------------------------------------------------
    // Data source resolution
    // -------------------------------------------------------------------------

    private function getDataSource(object $exportable): iterable
    {
        if ($exportable instanceof FromGenerator) {
            return $exportable->generator();
        }

        if ($exportable instanceof FromCollection) {
            return $exportable->collection();
        }

        if ($exportable instanceof FromQuery) {
            return $exportable->query()->get();
        }

        if ($exportable instanceof FromArray) {
            return $exportable->array();
        }

        return [];
    }

    // -------------------------------------------------------------------------
    // Writer factory
    // -------------------------------------------------------------------------

    private function createWriter(Spreadsheet $spreadsheet, string $writerType, object $exportable): IWriter
    {
        $writerClass = self::WRITER_MAP[$writerType] ?? self::WRITER_MAP['Xlsx'];
        /** @var IWriter $writer */
        $writer = new $writerClass($spreadsheet);

        if ($writer instanceof CsvWriter && $exportable instanceof WithCustomCsvSettings) {
            $settings = $exportable->getCsvSettings();
            isset($settings['delimiter'])              && $writer->setDelimiter($settings['delimiter']);
            isset($settings['enclosure'])              && $writer->setEnclosure($settings['enclosure']);
            isset($settings['line_ending'])            && $writer->setLineEnding($settings['line_ending']);
            isset($settings['use_bom'])                && $writer->setUseBOM((bool) $settings['use_bom']);
            isset($settings['excel_compatibility'])    && $writer->setExcelCompatibility((bool) $settings['excel_compatibility']);
            isset($settings['include_separator_line']) && $writer->setIncludeSeparatorLine((bool) $settings['include_separator_line']);
        }

        return $writer;
    }

    // -------------------------------------------------------------------------
    // Event dispatching
    // -------------------------------------------------------------------------

    private function fireEvent(object $exportable, object $event): void
    {
        if (!($exportable instanceof WithEvents)) {
            return;
        }

        $events    = $exportable->registerEvents();
        $eventClass = get_class($event);

        if (isset($events[$eventClass]) && is_callable($events[$eventClass])) {
            ($events[$eventClass])($event);
        }
    }
}
