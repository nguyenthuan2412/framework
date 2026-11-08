<?php

namespace Ryra\Core\AI\Memory\Repository;

use Ryra\Core\Contracts\AI\ChatMemoryRepository;

/**
 * Lưu lịch sử trong tiến trình. Mất khi worker khởi động lại - chỉ dùng cho
 * test hoặc hội thoại ngắn trong phạm vi một request.
 */
class InMemoryChatMemoryRepository implements ChatMemoryRepository
{
    /**
     * @var array<string, array<int, \Ryra\Core\AI\Message\Message>>
     */
    protected array $conversations = [];

    public function find(string $conversationId): array
    {
        return $this->conversations[$conversationId] ?? [];
    }

    public function save(string $conversationId, array $messages): void
    {
        $this->conversations[$conversationId] = array_values($messages);
    }

    public function delete(string $conversationId): void
    {
        unset($this->conversations[$conversationId]);
    }

    /**
     * @return array<int, string>
     */
    public function conversations(): array
    {
        return array_keys($this->conversations);
    }
}
