<?php

namespace Ryra\Core\AI\VectorStore\Drivers;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\VectorStore\Document;
use Ryra\Core\AI\VectorStore\SearchQuery;
use Ryra\Core\Contracts\AI\EmbeddingModel;
use Throwable;

/**
 * Kho vector chạy trên Elasticsearch, dùng kiểu dense_vector và tìm kiếm kNN.
 *
 * Đây là driver nên dùng khi số tài liệu lớn: việc chấm điểm do Elasticsearch
 * đảm nhiệm nên không phải nạp toàn bộ dữ liệu lên PHP.
 */
class ElasticsearchDriver extends AbstractVectorDriver
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        EmbeddingModel $embeddings,
        protected Client $client,
        array $config = [],
    ) {
        parent::__construct($embeddings, $config);
    }

    protected function store(array $documents, string $collection): void
    {
        $this->ensureIndex($collection);

        $operations = [];

        foreach ($documents as $document) {
            $operations[] = ['index' => ['_index' => $this->indexOf($collection), '_id' => $document->id]];
            $operations[] = [
                'content' => $document->content,
                'metadata' => (object) $document->metadata,
                'embedding' => $document->embedding,
            ];
        }

        if ($operations === []) {
            return;
        }

        $response = $this->client->bulk([
            'body' => $operations,
            // Thấy được ngay ở lần tìm kiếm kế tiếp, tránh bẫy "vừa ghi xong tìm không ra".
            'refresh' => 'wait_for',
        ]);

        if ($response->asArray()['errors'] ?? false) {
            throw new AIException('Elasticsearch bulk index failed: ' . $response->asString());
        }
    }

    protected function searchByVector(array $vector, SearchQuery $query): array
    {
        $collection = $this->collectionOf($query->getCollection());
        $topK = $query->getTopK();

        $body = [
            'knn' => [
                'field' => 'embedding',
                'query_vector' => $vector,
                'k' => $topK,
                // Xét rộng hơn topK để kết quả gần với tìm kiếm chính xác hơn.
                'num_candidates' => max($topK * 10, 100),
            ],
            '_source' => ['content', 'metadata'],
            'size' => $topK,
        ];

        if (($filters = $this->buildFilters($query->getFilter())) !== []) {
            $body['knn']['filter'] = ['bool' => ['filter' => $filters]];
        }

        try {
            $response = $this->client->search([
                'index' => $this->indexOf($collection),
                'body' => $body,
            ]);
        } catch (ClientResponseException $e) {
            // Index chưa tồn tại (chưa nạp gì, hoặc vừa clear) đồng nghĩa không
            // có kết quả - trả mảng rỗng cho đồng nhất với các driver khác.
            if ($e->getCode() === 404) {
                return [];
            }

            throw $e;
        }

        $documents = [];

        foreach ($response->asArray()['hits']['hits'] ?? [] as $hit) {
            // Elasticsearch dịch điểm cosine về [0, 1]; đưa lại về [-1, 1] cho
            // thống nhất với các driver tự tính bằng Similarity::cosine().
            $score = ((float) ($hit['_score'] ?? 0)) * 2 - 1;

            if ($score < $query->getThreshold()) {
                continue;
            }

            $documents[] = new Document(
                (string) ($hit['_id'] ?? ''),
                (string) ($hit['_source']['content'] ?? ''),
                (array) ($hit['_source']['metadata'] ?? []),
                [],
                $score,
            );
        }

        return $documents;
    }

    public function delete(array $ids, ?string $collection = null): void
    {
        if ($ids === []) {
            return;
        }

        $index = $this->indexOf($this->collectionOf($collection));
        $operations = [];

        foreach ($ids as $id) {
            $operations[] = ['delete' => ['_index' => $index, '_id' => $id]];
        }

        $this->client->bulk(['body' => $operations, 'refresh' => 'wait_for']);
    }

    public function clear(?string $collection = null): void
    {
        $index = $this->indexOf($this->collectionOf($collection));

        try {
            $this->client->indices()->delete(['index' => $index]);
        } catch (Throwable) {
            // Index chưa tồn tại thì coi như đã sạch.
        }
    }

    /**
     * Tạo index kèm mapping dense_vector nếu chưa có.
     */
    protected function ensureIndex(string $collection): void
    {
        $index = $this->indexOf($collection);

        if ($this->client->indices()->exists(['index' => $index])->asBool()) {
            return;
        }

        $dimensions = $this->embeddings->dimensions();

        if ($dimensions < 1) {
            throw new AIException(
                'Elasticsearch cần biết trước số chiều của vector. '
                . 'Khai báo "dimensions" trong config embedding driver.'
            );
        }

        $this->client->indices()->create([
            'index' => $index,
            'body' => [
                'mappings' => [
                    'properties' => [
                        'content' => ['type' => 'text'],
                        'metadata' => ['type' => 'object', 'enabled' => true],
                        'embedding' => [
                            'type' => 'dense_vector',
                            'dims' => $dimensions,
                            'index' => true,
                            'similarity' => 'cosine',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed> $filter
     * @return array<int, array<string, mixed>>
     */
    protected function buildFilters(array $filter): array
    {
        $filters = [];

        foreach ($filter as $key => $value) {
            $field = "metadata.{$key}";

            $filters[] = is_array($value)
                ? ['terms' => [$field => array_values($value)]]
                : ['term' => [$field => $value]];
        }

        return $filters;
    }

    protected function indexOf(string $collection): string
    {
        $prefix = (string) ($this->config['index_prefix'] ?? 'ai_vectors');

        return strtolower($prefix . '_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $collection));
    }
}
