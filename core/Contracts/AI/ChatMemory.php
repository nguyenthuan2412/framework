<?php

namespace Ryra\Core\Contracts\AI;

use Ryra\Core\AI\Message\Message;

interface ChatMemory
{
    /**
     * Lịch sử hội thoại, đã cắt bớt theo chính sách của từng cách nhớ.
     *
     * @return array<int, Message>
     */
    public function messages(string $conversationId): array;

    /**
     * Ghi thêm lượt nói vào lịch sử.
     */
    public function add(string $conversationId, Message ...$messages): void;

    /**
     * Xoá toàn bộ lịch sử của một cuộc hội thoại.
     */
    public function clear(string $conversationId): void;
}
