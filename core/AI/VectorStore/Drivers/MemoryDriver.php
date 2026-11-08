<?php

namespace Ryra\Core\AI\VectorStore\Drivers;

use Ryra\Core\AI\VectorStore\Document;
use Ryra\Core\AI\VectorStore\SearchQuery;

/**
 * Kho vector nằm trong bộ nhớ tiến trình.
 *
 * Dữ liệu mất khi worker khởi động lại nên chỉ dùng cho test hoặc tập tài liệu
 * nhỏ được nạp lại mỗi lần chạy.
 */
class MemoryDriver extends AbstractVectorDriver
{
    /**
     * @var array<string, array<string, Document>> [collection => [id => Document]]
     */
    protected array $documents = [];

    protected function store(array $documents, string $collection): void
    {
        foreach ($documents as $document) {
            $this->documents[$collection][$document->id] = $document;
        }
    }

    protected function searchByVector(array $vector, SearchQuery $query): array
    {
        $collection = $this->collectionOf($query->getCollection());
        $filter = $query->getFilter();

        $candidates = array_filter(
            $this->documents[$collection] ?? [],
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

        foreach ($ids as $id) {
            unset($this->documents[$name][$id]);
        }
    }

    public function clear(?string $collection = null): void
    {
        unset($this->documents[$this->collectionOf($collection)]);
    }

    public function count(?string $collection = null): int
    {
        return count($this->documents[$this->collectionOf($collection)] ?? []);
    }
}
