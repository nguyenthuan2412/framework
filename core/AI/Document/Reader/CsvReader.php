<?php

namespace Ryra\Core\AI\Document\Reader;

use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\VectorStore\Document;

/**
 * Đọc CSV, mỗi dòng thành một tài liệu dạng "cột: giá trị".
 *
 * Trình bày theo cặp tên cột - giá trị giúp model hiểu ngữ cảnh, thay vì đưa
 * một chuỗi giá trị trần không rõ nghĩa.
 */
class CsvReader extends TextReader
{
    /**
     * @param array<string, mixed>    $metadata
     * @param array<int, string>|null $contentColumns Chỉ đưa các cột này vào nội dung, null = tất cả.
     * @param array<int, string>      $metadataColumns Cột đưa vào metadata để lọc khi tìm kiếm.
     */
    public function __construct(
        string $path,
        array $metadata = [],
        protected string $separator = ',',
        protected ?array $contentColumns = null,
        protected array $metadataColumns = [],
    ) {
        parent::__construct($path, $metadata);
    }

    public function read(): array
    {
        if (!is_file($this->path)) {
            throw new AIException(sprintf('Document file not found: "%s".', $this->path));
        }

        $handle = fopen($this->path, 'r');

        if ($handle === false) {
            throw new AIException(sprintf('Unable to read document: "%s".', $this->path));
        }

        $documents = [];

        try {
            $headers = fgetcsv($handle, 0, $this->separator);

            if ($headers === false) {
                return [];
            }

            $headers = array_map(static fn (mixed $header): string => trim((string) $header), $headers);
            $line = 0;

            while (($row = fgetcsv($handle, 0, $this->separator)) !== false) {
                $line++;

                if ($row === [null] || $row === []) {
                    continue;
                }

                $record = $this->combine($headers, $row);
                $documents[] = new Document(
                    sprintf('%s#%d', $this->id(), $line),
                    $this->format($record),
                    array_merge($this->baseMetadata(), ['row' => $line], $this->extractMetadata($record)),
                );
            }
        } finally {
            fclose($handle);
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
        // Dòng thiếu/thừa cột so với header là chuyện thường gặp ở file thật.
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
            if ($this->contentColumns !== null && !in_array($column, $this->contentColumns, true)) {
                continue;
            }

            if ($value !== '') {
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
