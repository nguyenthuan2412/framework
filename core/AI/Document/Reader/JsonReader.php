<?php

namespace Ryra\Core\AI\Document\Reader;

use JsonException;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\VectorStore\Document;

/**
 * Đọc file JSON. Nếu dữ liệu là mảng thì mỗi phần tử thành một tài liệu.
 */
class JsonReader extends TextReader
{
    /**
     * @param array<string, mixed>    $metadata
     * @param array<int, string>|null $contentKeys  Khoá đưa vào nội dung, null = toàn bộ.
     * @param array<int, string>      $metadataKeys Khoá đưa vào metadata để lọc.
     * @param string|null             $pointer      Đường dẫn tới mảng cần đọc, ví dụ "data.items".
     */
    public function __construct(
        string $path,
        array $metadata = [],
        protected ?array $contentKeys = null,
        protected array $metadataKeys = [],
        protected ?string $pointer = null,
    ) {
        parent::__construct($path, $metadata);
    }

    public function read(): array
    {
        try {
            $data = json_decode($this->contents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new AIException(
                sprintf('Invalid JSON in "%s": %s', $this->path, $e->getMessage()),
                previous: $e,
            );
        }

        $data = $this->resolvePointer($data);
        $records = is_array($data) && array_is_list($data) ? $data : [$data];

        $documents = [];

        foreach ($records as $index => $record) {
            $record = is_array($record) ? $record : ['value' => $record];

            $documents[] = new Document(
                sprintf('%s#%d', $this->id(), $index),
                $this->format($record),
                array_merge($this->baseMetadata(), $this->extractMetadata($record)),
            );
        }

        return $documents;
    }

    protected function resolvePointer(mixed $data): mixed
    {
        if ($this->pointer === null) {
            return $data;
        }

        foreach (explode('.', $this->pointer) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                throw new AIException(
                    sprintf('JSON pointer "%s" not found in "%s".', $this->pointer, $this->path)
                );
            }

            $data = $data[$segment];
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $record
     */
    protected function format(array $record): string
    {
        $lines = [];

        foreach ($record as $key => $value) {
            if ($this->contentKeys !== null && !in_array($key, $this->contentKeys, true)) {
                continue;
            }

            $lines[] = sprintf('%s: %s', $key, is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value, JSON_UNESCAPED_UNICODE));
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  array<string, mixed> $record
     * @return array<string, mixed>
     */
    protected function extractMetadata(array $record): array
    {
        $metadata = [];

        foreach ($this->metadataKeys as $key) {
            if (array_key_exists($key, $record) && is_scalar($record[$key])) {
                $metadata[$key] = $record[$key];
            }
        }

        return $metadata;
    }
}
