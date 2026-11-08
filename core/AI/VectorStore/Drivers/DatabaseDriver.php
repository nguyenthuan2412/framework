<?php

namespace Ryra\Core\AI\VectorStore\Drivers;

use Ryra\Core\AI\VectorStore\Document;
use Ryra\Core\AI\VectorStore\SearchQuery;
use Ryra\Core\Contracts\AI\EmbeddingModel;
use Ryra\Core\Contracts\Database\Connection;

/**
 * Kho vector lưu trong bảng SQL sẵn có, không cần dựng thêm dịch vụ.
 *
 * Độ tương đồng được tính ở phía PHP nên toàn bộ tài liệu của collection sẽ
 * được nạp lên khi tìm kiếm - phù hợp tới khoảng vài chục nghìn bản ghi.
 * Vượt quá mức đó nên chuyển sang driver elasticsearch.
 *
 * Bảng cần tạo trước:
 *
 *   CREATE TABLE ai_vector_documents (
 *       id          VARCHAR(64)  NOT NULL PRIMARY KEY,
 *       collection  VARCHAR(120) NOT NULL,
 *       content     LONGTEXT     NOT NULL,
 *       metadata    JSON         NULL,
 *       embedding   JSON         NOT NULL,
 *       created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *       INDEX idx_ai_vector_collection (collection)
 *   );
 */
class DatabaseDriver extends AbstractVectorDriver
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
                // Xoá rồi chèn lại để cùng một câu lệnh chạy được trên cả MySQL lẫn Postgres.
                $this->connection->delete("DELETE FROM {$table} WHERE id = ?", [$document->id]);

                $this->connection->insert(
                    "INSERT INTO {$table} (id, collection, content, metadata, embedding) VALUES (?, ?, ?, ?, ?)",
                    [
                        $document->id,
                        $collection,
                        $document->content,
                        json_encode($document->metadata, JSON_UNESCAPED_UNICODE),
                        json_encode($document->embedding),
                    ],
                );
            }
        });
    }

    protected function searchByVector(array $vector, SearchQuery $query): array
    {
        $table = $this->table();
        $collection = $this->collectionOf($query->getCollection());
        $filter = $query->getFilter();

        $rows = $this->connection->select(
            "SELECT id, content, metadata, embedding FROM {$table} WHERE collection = ?",
            [$collection],
        );

        $candidates = [];

        foreach ($rows as $row) {
            $document = $this->hydrate($row);

            if ($this->matchesFilter($document->metadata, $filter)) {
                $candidates[] = $document;
            }
        }

        return array_map(
            static fn (Document $document): Document => $document->withoutEmbedding(),
            $this->rank($vector, $candidates, $query),
        );
    }

    public function delete(array $ids, ?string $collection = null): void
    {
        if ($ids === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        $this->connection->delete(
            sprintf(
                'DELETE FROM %s WHERE collection = ? AND id IN (%s)',
                $this->table(),
                $placeholders,
            ),
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
     * @param array<string, mixed>|object $row
     */
    protected function hydrate(mixed $row): Document
    {
        $row = (array) $row;

        return new Document(
            (string) $row['id'],
            (string) $row['content'],
            (array) (json_decode((string) ($row['metadata'] ?? '[]'), true) ?? []),
            array_map('floatval', (array) (json_decode((string) ($row['embedding'] ?? '[]'), true) ?? [])),
        );
    }

    protected function table(): string
    {
        return (string) ($this->config['table'] ?? 'ai_vector_documents');
    }
}
