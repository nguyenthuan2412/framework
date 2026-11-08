<?php

namespace Ryra\Core\AI\VectorStore\Drivers;

use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\VectorStore\Document;
use Ryra\Core\AI\VectorStore\SearchQuery;
use Ryra\Core\Contracts\AI\EmbeddingModel;

/**
 * Kho vector lưu trên đĩa, mỗi collection là một file JSON Lines.
 *
 * Phù hợp cho tập tài liệu vừa phải (vài nghìn bản ghi) và không cần thêm
 * hạ tầng. Toàn bộ file được nạp vào bộ nhớ khi tìm kiếm.
 */
class FileDriver extends AbstractVectorDriver
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(EmbeddingModel $embeddings, array $config = [])
    {
        parent::__construct($embeddings, $config);

        $this->ensureDirectoryExists($this->folder());
    }

    protected function store(array $documents, string $collection): void
    {
        $existing = $this->read($collection);

        foreach ($documents as $document) {
            $existing[$document->id] = $document;
        }

        $this->write($collection, $existing);
    }

    protected function searchByVector(array $vector, SearchQuery $query): array
    {
        $collection = $this->collectionOf($query->getCollection());
        $filter = $query->getFilter();

        $candidates = array_filter(
            $this->read($collection),
            fn (Document $document): bool => $this->matchesFilter($document->metadata, $filter),
        );

        return array_map(
            static fn (Document $document): Document => $document->withoutEmbedding(),
            $this->rank($vector, array_values($candidates), $query),
        );
    }

    public function delete(array $ids, ?string $collection = null): void
    {
        $name = $this->collectionOf($collection);
        $documents = $this->read($name);

        foreach ($ids as $id) {
            unset($documents[$id]);
        }

        $this->write($name, $documents);
    }

    public function clear(?string $collection = null): void
    {
        $file = $this->pathOf($this->collectionOf($collection));

        if (is_file($file)) {
            unlink($file);
        }
    }

    /**
     * @return array<string, Document>
     */
    protected function read(string $collection): array
    {
        $file = $this->pathOf($collection);

        if (!is_file($file)) {
            return [];
        }

        $handle = fopen($file, 'r');

        if ($handle === false) {
            return [];
        }

        $documents = [];

        try {
            if (!flock($handle, LOCK_SH)) {
                return [];
            }

            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $decoded = json_decode($line, true);

                if (is_array($decoded)) {
                    $document = Document::fromArray($decoded);
                    $documents[$document->id] = $document;
                }
            }

            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return $documents;
    }

    /**
     * @param array<string, Document> $documents
     */
    protected function write(string $collection, array $documents): void
    {
        $lines = '';

        foreach ($documents as $document) {
            $lines .= json_encode($document->toArray(), JSON_UNESCAPED_UNICODE) . "\n";
        }

        // Ghi ra file tạm rồi đổi tên để không bao giờ đọc phải file dở dang.
        $file = $this->pathOf($collection);
        $temp = $file . '.' . uniqid('tmp', true);

        if (file_put_contents($temp, $lines, LOCK_EX) === false) {
            throw new AIException(sprintf('Unable to write vector store file "%s".', $file));
        }

        rename($temp, $file);
    }

    protected function pathOf(string $collection): string
    {
        return $this->folder() . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9_\-]/', '_', $collection) . '.jsonl';
    }

    protected function folder(): string
    {
        return rtrim((string) ($this->config['path'] ?? sys_get_temp_dir()), DIRECTORY_SEPARATOR);
    }

    protected function ensureDirectoryExists(string $folder): void
    {
        if (is_dir($folder)) {
            return;
        }

        if (!mkdir($folder, 0755, true) && !is_dir($folder)) {
            throw new AIException(
                sprintf('Unable to create vector store directory "%s". Kiểm tra lại quyền ghi.', $folder)
            );
        }
    }
}
