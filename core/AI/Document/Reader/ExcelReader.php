<?php

namespace Ryra\Core\AI\Document\Reader;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\VectorStore\Document;

/**
 * Đọc file Excel (.xlsx, .xls) qua phpoffice/phpspreadsheet.
 *
 * Dòng đầu của mỗi sheet được coi là tiêu đề cột; mỗi dòng dữ liệu thành một
 * tài liệu dạng "cột: giá trị".
 */
class ExcelReader extends TextReader
{
    /**
     * @param array<string, mixed>    $metadata
     * @param array<int, string>|null $sheets          Chỉ đọc các sheet này, null = tất cả.
     * @param array<int, string>      $metadataColumns Cột đưa vào metadata để lọc.
     */
    public function __construct(
        string $path,
        array $metadata = [],
        protected ?array $sheets = null,
        protected array $metadataColumns = [],
    ) {
        parent::__construct($path, $metadata);
    }

    public function read(): array
    {
        if (!class_exists(IOFactory::class)) {
            throw new AIException('Cần cài gói phpoffice/phpspreadsheet để đọc file Excel.');
        }

        if (!is_file($this->path)) {
            throw new AIException(sprintf('Document file not found: "%s".', $this->path));
        }

        $reader = IOFactory::createReaderForFile($this->path);
        // Chỉ cần giá trị, bỏ định dạng cho nhẹ bộ nhớ.
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($this->path);

        $documents = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $name = $sheet->getTitle();

            if ($this->sheets !== null && !in_array($name, $this->sheets, true)) {
                continue;
            }

            $rows = $sheet->toArray(null, true, false, false);
            $headers = array_map(
                static fn (mixed $header): string => trim((string) $header),
                array_shift($rows) ?? [],
            );

            foreach ($rows as $index => $row) {
                $record = $this->combine($headers, $row);
                $content = $this->format($record);

                if (trim($content) === '') {
                    continue;
                }

                $documents[] = new Document(
                    sprintf('%s#%s#%d', $this->id(), $name, $index),
                    $content,
                    array_merge(
                        $this->baseMetadata(),
                        ['sheet' => $name, 'row' => $index + 2],
                        $this->extractMetadata($record),
                    ),
                );
            }
        }

        return $documents;
    }

    /**
     * @param  array<int, string> $headers
     * @param  array<int, mixed>  $row
     * @return array<string, string>
     */
    protected function combine(array $headers, array $row): array
    {
        $row = array_pad(array_slice($row, 0, count($headers)), count($headers), '');

        return array_combine($headers, array_map(static fn (mixed $value): string => trim((string) $value), $row));
    }

    /**
     * @param array<string, string> $record
     */
    protected function format(array $record): string
    {
        $lines = [];

        foreach ($record as $column => $value) {
            if ($column !== '' && $value !== '') {
                $lines[] = sprintf('%s: %s', $column, $value);
            }
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  array<string, string> $record
     * @return array<string, string>
     */
    protected function extractMetadata(array $record): array
    {
        $metadata = [];

        foreach ($this->metadataColumns as $column) {
            if (isset($record[$column])) {
                $metadata[$column] = $record[$column];
            }
        }

        return $metadata;
    }
}
