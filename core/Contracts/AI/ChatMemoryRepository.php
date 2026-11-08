<?php

namespace Ryra\Core\Contracts\AI;

use Ryra\Core\AI\Message\Message;

interface ChatMemoryRepository
{
    /**
     * @return array<int, Message>
     */
    public function find(string $conversationId): array;

    /**
     * @param array<int, Message> $messages
     */
    public function save(string $conversationId, array $messages): void;

    public function delete(string $conversationId): void;
}
