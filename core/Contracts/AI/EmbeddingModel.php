<?php

namespace Ryra\Core\Contracts\AI;

interface EmbeddingModel
{
    /**
     * Chuyển một đoạn văn bản thành vector.
     *
     * @return array<int, float>
     */
    public function embed(string $text): array;

    /**
     * Chuyển nhiều đoạn văn bản thành vector trong một lần gọi.
     *
     * @param  array<int, string> $texts
     * @return array<int, array<int, float>>
     */
    public function embedBatch(array $texts): array;

    /**
     * Số chiều của vector do model này sinh ra.
     */
    public function dimensions(): int;

    /**
     * Tên định danh của driver embedding.
     */
    public function name(): string;
}
