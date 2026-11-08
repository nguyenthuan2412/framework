<?php

namespace Ryra\Core\AI\Memory;

use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Message\AssistantMessage;
use Ryra\Core\AI\Message\Message;
use Ryra\Core\AI\Message\SystemMessage;
use Ryra\Core\AI\Message\ToolResultMessage;
use Ryra\Core\Contracts\AI\ChatMemory;
use Ryra\Core\Contracts\AI\ChatMemoryRepository;

/**
 * Chỉ giữ N lượt nói gần nhất.
 *
 * Hội thoại dài mà gửi hết lên sẽ chạm giới hạn ngữ cảnh và tốn tiền vô ích.
 * Message system luôn được giữ lại vì đó là phần định nghĩa vai trò của trợ lý.
 *
 * Cửa sổ được cắt sao cho không bỏ sót kết quả tool: một message tool_result mà
 * mất lượt assistant yêu cầu gọi tool tương ứng sẽ bị nhà cung cấp từ chối.
 */
class MessageWindowChatMemory implements ChatMemory
{
    public function __construct(
        protected ChatMemoryRepository $repository,
        protected int $maxMessages = 20,
    ) {
        if ($maxMessages < 1) {
            throw new AIException("maxMessages must be at least 1, {$maxMessages} given.");
        }
    }

    public function messages(string $conversationId): array
    {
        return $this->repository->find($conversationId);
    }

    public function add(string $conversationId, Message ...$messages): void
    {
        if ($messages === []) {
            return;
        }

        $this->repository->save(
            $conversationId,
            $this->window([...$this->repository->find($conversationId), ...$messages]),
        );
    }

    public function clear(string $conversationId): void
    {
        $this->repository->delete($conversationId);
    }

    /**
     * @param  array<int, Message> $messages
     * @return array<int, Message>
     */
    protected function window(array $messages): array
    {
        $system = array_values(array_filter(
            $messages,
            static fn (Message $message): bool => $message instanceof SystemMessage,
        ));

        $conversation = array_values(array_filter(
            $messages,
            static fn (Message $message): bool => !$message instanceof SystemMessage,
        ));

        $limit = max(1, $this->maxMessages - count($system));

        if (count($conversation) > $limit) {
            $conversation = array_slice($conversation, -$limit);
            $conversation = $this->dropOrphanToolResults($conversation);
        }

        return [...$system, ...$conversation];
    }

    /**
     * Bỏ các tool_result ở đầu cửa sổ không còn lượt assistant tương ứng.
     *
     * @param  array<int, Message> $messages
     * @return array<int, Message>
     */
    protected function dropOrphanToolResults(array $messages): array
    {
        $known = [];

        foreach ($messages as $message) {
            if ($message instanceof AssistantMessage) {
                foreach ($message->toolCalls as $call) {
                    $known[$call->id] = true;
                }
            }
        }

        return array_values(array_filter(
            $messages,
            static fn (Message $message): bool => !$message instanceof ToolResultMessage
                || isset($known[$message->toolCallId]),
        ));
    }
}
