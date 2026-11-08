<?php

namespace Ryra\Core\AI\Tests\Fixtures;

use Ryra\Core\Contracts\AI\EmbeddingModel;

/**
 * Embedding giả lập theo túi từ: văn bản càng chung từ vựng thì vector càng gần.
 * Nhờ vậy test kiểm tra được thứ tự kết quả mà không cần gọi API thật.
 */
class FakeEmbeddingModel implements EmbeddingModel
{
    public int $calls = 0;

    /**
     * @param array<int, string> $vocabulary
     */
    public function __construct(
        protected array $vocabulary = ['tour', 'guide', 'chi', 'phi', 'khach', 'san'],
    ) {}

    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0];
    }

    public function embedBatch(array $texts): array
    {
        $this->calls++;

        return array_map(function (string $text): array {
            $words = preg_split('/\s+/', mb_strtolower($text)) ?: [];

            return array_map(
                static fn (string $term): float => (float) count(array_keys($words, $term, true)),
                $this->vocabulary,
            );
        }, array_values($texts));
    }

    public function dimensions(): int
    {
        return count($this->vocabulary);
    }

    public function name(): string
    {
        return 'fake';
    }
}
