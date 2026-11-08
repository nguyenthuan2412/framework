<?php

namespace Ryra\Core\AI\VectorStore\Drivers;

use Ryra\Core\AI\VectorStore\Document;
use Ryra\Core\AI\VectorStore\SearchQuery;
use Ryra\Core\Contracts\AI\EmbeddingModel;
use Ryra\Core\Contracts\Database\Connection;

/**
 * Kho vector chạy trên PostgreSQL + extension pgvector.
 *
 * Khác với DatabaseDriver (tính tương đồng bằng PHP), driver này để Postgres
 * tìm bằng chỉ mục HNSW nên không phải nạp dữ liệu lên ứng dụng - dùng được
 * với hàng triệu bản ghi mà vẫn giữ dữ liệu trong database sẵn có.
 *
 * Chuẩn bị một lần:
 *
 *   CREATE EXTENSION IF NOT EXISTS vector;
 *
 *   CREATE TABLE ai_vector_documents (
 *       id         VARCHAR(64)  PRIMARY KEY,
 *       collection VARCHAR(120) NOT NULL,
 *       content    TEXT         NOT NULL,
 *       metadata   JSONB        NOT NULL DEFAULT '{}'::jsonb,
 *       embedding  VECTOR(1536) NOT NULL,
 *       created_at TIMESTAMPTZ  NOT NULL DEFAULT now()
 *   );
 *
 *   CREATE INDEX ai_vector_documents_embedding_idx
 *       ON ai_vector_documents USING hnsw (embedding vector_cosine_ops);
 *
 *   CREATE INDEX ai_vector_documents_collection_idx ON ai_vector_documents (collection);
 *
 * Số chiều trong VECTOR(...) phải khớp "dimensions" của embedding driver.
 */
class PgVectorDriver extends AbstractVectorDriver
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        EmbeddingModel $embeddings,
        protected Connection $connection,
        array $config = [],
    ) {
        parent::__construct($embeddings, $config);
    }

    protected function store(array $documents, string $collection): void
    {
        $table = $this->table();

        $this->connection->transaction(function () use ($documents, $collection, $table): void {
            foreach ($documents as $document) {
                $this->connection->statement(
                    "INSERT INTO {$table} (id, collection, content, metadata, embedding)
                     VALUES (?, ?, ?, ?::jsonb, ?::vector)
                     ON CONFLICT (id) DO UPDATE SET
                         collection = EXCLUDED.collection,
                         content    = EXCLUDED.content,
                         metadata   = EXCLUDED.metadata,
                         embedding  = EXCLUDED.embedding",
                    [
                        $document->id,
                        $collection,
                        $document->content,
                        json_encode((object) $document->metadata, JSON_UNESCAPED_UNICODE),
                        $this->toVector($document->embedding),
                    ],
                );
            }
        });
    }

    protected function searchByVector(array $vector, SearchQuery $query): array
    {
        $literal = $this->toVector($vector);
        $bindings = [$literal, $this->collectionOf($query->getCollection())];
        $conditions = ['collection = ?'];

        foreach ($query->getFilter() as $key => $value) {
            if (is_array($value)) {
                $placeholders = implode(', ', array_fill(0, count($value), '?'));
                $conditions[] = "metadata->>? IN ({$placeholders})";
                $bindings[] = $key;
                array_push($bindings, ...array_map('strval', array_values($value)));
                continue;
            }

            $conditions[] = 'metadata->>? = ?';
            $bindings[] = $key;
            $bindings[] = (string) $value;
        }

        // Toán tử <=> của pgvector là khoảng cách cosine, đổi về độ tương đồng
        // để thống nhất quy ước "càng lớn càng giống" với các driver khác.
        $sql = sprintf(
            'SELECT id, content, metadata, 1 - (embedding <=> ?::vector) AS score
             FROM %s WHERE %s ORDER BY embedding <=> ?::vector LIMIT ?',
            $this->table(),
            implode(' AND ', $conditions),
        );

        $bindings[] = $literal;
        $bindings[] = $query->getTopK();

        $documents = [];

        foreach ($this->connection->select($sql, $bindings) as $row) {
            $row = (array) $row;
            $score = (float) $row['score'];

            if ($score < $query->getThreshold()) {
                continue;
            }

            $documents[] = new Document(
                (string) $row['id'],
                (string) $row['content'],
                (array) (json_decode((string) ($row['metadata'] ?? '{}'), true) ?? []),
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

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        $this->connection->delete(
            sprintf('DELETE FROM %s WHERE collection = ? AND id IN (%s)', $this->table(), $placeholders),
            [$this->collectionOf($collection), ...array_values($ids)],
        );
    }

    public function clear(?string $collection = null): void
    {
        $this->connection->delete(
            sprintf('DELETE FROM %s WHERE collection = ?', $this->table()),
            [$this->collectionOf($collection)],
        );
    }

    /**
     * pgvector nhận vector dưới dạng chuỗi "[0.1,0.2,...]".
     *
     * @param array<int, float> $vector
     */
    protected function toVector(array $vector): string
    {
        return '[' . implode(',', array_map(
            static fn (float $value): string => rtrim(rtrim(sprintf('%.8F', $value), '0'), '.') ?: '0',
            $vector,
        )) . ']';
    }

    protected function table(): string
    {
        return (string) ($this->config['table'] ?? 'ai_vector_documents');
    }
}
