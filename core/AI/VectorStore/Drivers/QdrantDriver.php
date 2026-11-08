<?php

namespace Ryra\Core\AI\VectorStore\Drivers;

use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Exceptions\ProviderException;
use Ryra\Core\AI\Http\Transport;
use Ryra\Core\AI\VectorStore\Document;
use Ryra\Core\AI\VectorStore\SearchQuery;
use Ryra\Core\Contracts\AI\EmbeddingModel;

/**
 * Kho vector chạy trên Qdrant - cơ sở dữ liệu vector chuyên dụng.
 *
 * Toàn bộ việc đánh chỉ mục HNSW và chấm điểm do Qdrant đảm nhiệm; ứng dụng
 * chỉ gửi vector truy vấn và nhận về topK.
 */
class QdrantDriver extends AbstractVectorDriver
{
    protected Transport $transport;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(EmbeddingModel $embeddings, array $config = [], ?Transport $transport = null)
    {
        parent::__construct($embeddings, $config);

        $this->transport = $transport ?? new Transport(
            'qdrant',
            (float) ($config['timeout'] ?? 30),
            (float) ($config['connect_timeout'] ?? 5),
            (int) ($config['retries'] ?? 2),
        );
    }

    protected function store(array $documents, string $collection): void
    {
        $this->ensureCollection($collection);

        $points = [];

        foreach ($documents as $document) {
            $points[] = [
                'id' => $this->pointId($document->id),
                'vector' => $document->embedding,
                'payload' => [
                    'content' => $document->content,
                    'metadata' => (object) $document->metadata,
                    // Giữ lại id gốc vì Qdrant chỉ nhận id dạng số hoặc UUID.
                    'source_id' => $document->id,
                ],
            ];
        }

        // wait=true để lần tìm kiếm ngay sau đó thấy được dữ liệu vừa ghi.
        $this->transport->put(
            $this->url($collection, '/points?wait=true'),
            $this->headers(),
            ['points' => $points],
        );
    }

    protected function searchByVector(array $vector, SearchQuery $query): array
    {
        $body = [
            'vector' => $vector,
            'limit' => $query->getTopK(),
            'with_payload' => true,
        ];

        if ($query->getThreshold() > 0) {
            $body['score_threshold'] = $query->getThreshold();
        }

        if (($conditions = $this->buildFilter($query->getFilter())) !== []) {
            $body['filter'] = ['must' => $conditions];
        }

        try {
            $response = $this->transport->post(
                $this->url($this->collectionOf($query->getCollection()), '/points/search'),
                $this->headers(),
                $body,
            );
        } catch (ProviderException $e) {
            // Collection chưa tồn tại (chưa nạp gì, hoặc vừa clear) đồng nghĩa
            // không có kết quả - phải trả mảng rỗng như các driver khác, không
            // được ném lỗi.
            if ($e->status === 404) {
                return [];
            }

            throw $e;
        }

        $documents = [];

        foreach ($response['result'] ?? [] as $hit) {
            $payload = $hit['payload'] ?? [];

            $documents[] = new Document(
                (string) ($payload['source_id'] ?? $hit['id'] ?? ''),
                (string) ($payload['content'] ?? ''),
                (array) ($payload['metadata'] ?? []),
                [],
                (float) ($hit['score'] ?? 0),
            );
        }

        return $documents;
    }

    public function delete(array $ids, ?string $collection = null): void
    {
        if ($ids === []) {
            return;
        }

        try {
            $this->transport->post(
                $this->url($this->collectionOf($collection), '/points/delete?wait=true'),
                $this->headers(),
                ['points' => array_map(fn (string $id): string => $this->pointId($id), array_values($ids))],
            );
        } catch (ProviderException $e) {
            // Xoá trong collection không tồn tại thì coi như đã xong.
            if ($e->status !== 404) {
                throw $e;
            }
        }
    }

    public function clear(?string $collection = null): void
    {
        try {
            $this->transport->delete($this->url($this->collectionOf($collection)), $this->headers());
        } catch (ProviderException $e) {
            // Collection chưa tồn tại thì coi như đã sạch.
            if ($e->status !== 404) {
                throw $e;
            }
        }
    }

    /**
     * Tạo collection với cấu hình cosine nếu chưa có.
     */
    protected function ensureCollection(string $collection): void
    {
        try {
            $this->transport->get($this->url($collection), $this->headers());
            return;
        } catch (ProviderException $e) {
            if ($e->status !== 404) {
                throw $e;
            }
        }

        $dimensions = $this->embeddings->dimensions();

        if ($dimensions < 1) {
            throw new AIException(
                'Qdrant cần biết trước số chiều của vector. '
                . 'Khai báo "dimensions" trong config embedding driver.'
            );
        }

        $this->transport->put($this->url($collection), $this->headers(), [
            'vectors' => [
                'size' => $dimensions,
                'distance' => 'Cosine',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed> $filter
     * @return array<int, array<string, mixed>>
     */
    protected function buildFilter(array $filter): array
    {
        $conditions = [];

        foreach ($filter as $key => $value) {
            $field = "metadata.{$key}";

            $conditions[] = is_array($value)
                ? ['key' => $field, 'match' => ['any' => array_values($value)]]
                : ['key' => $field, 'match' => ['value' => $value]];
        }

        return $conditions;
    }

    /**
     * Qdrant chỉ chấp nhận id là số nguyên không dấu hoặc UUID.
     * Id không hợp lệ được băm về UUID cố định để luôn ánh xạ 1-1.
     */
    protected function pointId(string $id): string
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) === 1) {
            return $id;
        }

        $hash = md5($id);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        $headers = [];

        if (!empty($this->config['api_key'])) {
            $headers['api-key'] = (string) $this->config['api_key'];
        }

        return $headers;
    }

    protected function url(string $collection, string $suffix = ''): string
    {
        $host = rtrim((string) ($this->config['host'] ?? 'http://localhost:6333'), '/');
        $prefix = (string) ($this->config['collection_prefix'] ?? '');

        return sprintf('%s/collections/%s%s%s', $host, $prefix, $collection, $suffix);
    }
}
