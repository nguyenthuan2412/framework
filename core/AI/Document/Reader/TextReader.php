<?php

namespace Ryra\Core\AI\Document\Reader;

use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\VectorStore\Document;
use Ryra\Core\Contracts\AI\DocumentReader;

/**
 * Đọc file văn bản thuần. Cũng là lớp cha cho các reader đọc từ file khác.
 */
class TextReader implements DocumentReader
{
    /**
     * @param array<string, mixed> $metadata Metadata gắn thêm cho mọi tài liệu đọc được.
     */
    public function __construct(
        protected string $path,
        protected array $metadata = [],
    ) {}

    public function read(): array
    {
        return [new Document($this->id(), $this->content(), $this->baseMetadata())];
    }

    protected function content(): string
    {
        return $this->contents();
    }

    /**
     * Đọc nội dung thô của file, báo lỗi rõ ràng nếu không đọc được.
     */
    protected function contents(): string
    {
        if (!is_file($this->path)) {
            throw new AIException(sprintf('Document file not found: "%s".', $this->path));
        }

        $contents = file_get_contents($this->path);

        if ($contents === false) {
            throw new AIException(sprintf('Unable to read document: "%s".', $this->path));
        }

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseMetadata(): array
    {
        return array_merge([
            'source' => $this->path,
            'file_name' => basename($this->path),
        ], $this->metadata);
    }

    protected function id(): string
    {
        return md5($this->path);
    }
}
