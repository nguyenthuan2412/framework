<?php

namespace Ryra\Core\AI\VectorStore\Drivers;

use Ryra\Core\AI\VectorStore\Document;
use Ryra\Core\AI\VectorStore\SearchQuery;
use Ryra\Core\AI\VectorStore\Similarity;
use Ryra\Core\Contracts\AI\EmbeddingModel;
use Ryra\Core\Contracts\AI\VectorStore;

abstract class AbstractVectorDriver implements VectorStore
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        protected EmbeddingModel $embeddings,
        protected array $config = [],
    ) {}

    public function add(array|Document $documents, ?string $collection = null): void
    {
        $documents = $documents instanceof Document ? [$documents] : $documents;

        if ($documents === []) {
            return;
        }

        $this->store($this->embedMissing($documents), $this->collectionOf($collection));
    }

    public function search(SearchQuery $query): array
    {
        $query->guard();

        $vector = $query->getVector() ?? $this->embeddings->embed((string) $query->getText());

        return $this->searchByVector($vector, $query);
    }

    /**
     * Nhúng những tài liệu chưa có vector, gọi API một lần cho cả lô.
     *
     * @param  array<int, Document> $documents
     * @return array<int, Document>
     */
    protected function embedMissing(array $documents): array
    {
        $pending = [];

        foreach ($documents as $index => $document) {
            if (!$document->hasEmbedding()) {
                $pending[$index] = $document->content;
            }
        }

        if ($pending === []) {
            return $documents;
        }

        $vectors = $this->embeddings->embedBatch(array_values($pending));
        $position = 0;

        foreach (array_keys($pending) as $index) {
            $documents[$index] = $documents[$index]->withEmbedding($vectors[$position] ?? []);
            $position++;
        }

        return $documents;
    }

    /**
     * Chấm điểm, lọc ngưỡng và lấy topK. Dùng cho các driver tự tính tương đồng
     * ở phía PHP (memory, file, database).
     *
     * @param  array<int, float>    $vector
     * @param  array<int, Document> $candidates
     * @return array<int, Document>
     */
    protected function rank(array $vector, array $candidates, SearchQuery $query): array
    {
        $scored = [];

        foreach ($candidates as $document) {
            if (!$document->hasEmbedding()) {
                continue;
            }

            $score = Similarity::cosine($vector, $document->embedding);

            if ($score < $query->getThreshold()) {
                continue;
            }

            $scored[] = $document->withScore($score);
        }

        usort($scored, static fn (Document $a, Document $b): int => $b->score <=> $a->score);

        return array_slice($scored, 0, $query->getTopK());
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $filter
     */
    protected function matchesFilter(array $metadata, array $filter): bool
    {
        foreach ($filter as $key => $expected) {
            $actual = $metadata[$key] ?? null;

            // Mảng nghĩa là "thuộc một trong các giá trị".
            $matched = is_array($expected)
                ? in_array($actual, $expected, false)
                : $actual == $expected;

            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    protected function collectionOf(?string $collection): string
    {
        return $collection ?? (string) ($this->config['collection'] ?? 'default');
    }

    /**
     * @param array<int, Document> $documents
     */
    abstract protected function store(array $documents, string $collection): void;

    /**
     * @param  array<int, float> $vector
     * @return array<int, Document>
     */
    abstract protected function searchByVector(array $vector, SearchQuery $query): array;
}
