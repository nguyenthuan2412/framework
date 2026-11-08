<?php

namespace Ryra\Core\AI\VectorStore;

use Ryra\Core\Support\Arrayable;
use Ryra\Core\Support\Str;

class Document implements Arrayable
{
    /**
     * @param array<string, mixed> $metadata Dữ liệu kèm theo để lọc khi tìm kiếm.
     * @param array<int, float>    $embedding
     * @param float|null           $score    Điểm tương đồng, chỉ có sau khi search.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $content,
        public readonly array $metadata = [],
        public readonly array $embedding = [],
        public readonly ?float $score = null,
    ) {}

    /**
     * @param array<string, mixed> $metadata
     */
    public static function make(string $content, array $metadata = [], ?string $id = null): static
    {
        return new static($id ?? Str::uuid7(), $content, $metadata);
    }

    public function hasEmbedding(): bool
    {
        return $this->embedding !== [];
    }

    /**
     * @param array<int, float> $embedding
     */
    public function withEmbedding(array $embedding): static
    {
        return new static($this->id, $this->content, $this->metadata, $embedding, $this->score);
    }

    public function withScore(float $score): static
    {
        return new static($this->id, $this->content, $this->metadata, $this->embedding, $score);
    }

    /**
     * Bỏ vector đi cho nhẹ khi trả kết quả ra ngoài.
     */
    public function withoutEmbedding(): static
    {
        return new static($this->id, $this->content, $this->metadata, [], $this->score);
    }

    public function metadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): static
    {
        return new static(
            (string) ($row['id'] ?? Str::uuid7()),
            (string) ($row['content'] ?? ''),
            (array) ($row['metadata'] ?? []),
            array_map('floatval', (array) ($row['embedding'] ?? [])),
            isset($row['score']) ? (float) $row['score'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'content' => $this->content,
            'metadata' => $this->metadata,
            'embedding' => $this->embedding ?: null,
            'score' => $this->score,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
