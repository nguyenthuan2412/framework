<?php

namespace Ryra\Core\AI\Memory\Repository;

use Ryra\Core\AI\Message\Message;
use Ryra\Core\AI\Message\MessageFactory;
use Ryra\Core\Contracts\AI\ChatMemoryRepository;
use Ryra\Core\Contracts\Database\Connection;

/**
 * Lưu lịch sử hội thoại xuống database.
 *
 * Bảng cần tạo trước:
 *
 *   CREATE TABLE ai_chat_messages (
 *       id              BIGINT       NOT NULL PRIMARY KEY,
 *       conversation_id VARCHAR(120) NOT NULL,
 *       position        INT          NOT NULL,
 *       role            VARCHAR(20)  NOT NULL,
 *       payload         JSON         NOT NULL,
 *       created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *       INDEX idx_ai_chat_conversation (conversation_id, position)
 *   );
 *
 * Cột id dùng Snowflake theo quy ước chung của dự án.
 */
class DatabaseChatMemoryRepository implements ChatMemoryRepository
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        protected Connection $connection,
        protected array $config = [],
    ) {}

    public function find(string $conversationId): array
    {
        $rows = $this->connection->select(
            sprintf('SELECT payload FROM %s WHERE conversation_id = ? ORDER BY position ASC', $this->table()),
            [$conversationId],
        );

        $messages = [];

        foreach ($rows as $row) {
            $payload = json_decode((string) ((array) $row)['payload'], true);

            if (is_array($payload)) {
                $messages[] = MessageFactory::fromArray($payload);
            }
        }

        return $messages;
    }

    public function save(string $conversationId, array $messages): void
    {
        $table = $this->table();

        // Ghi đè cả cuộc hội thoại: cửa sổ trượt có thể đã cắt bớt lượt cũ,
        // nên chèn thêm không phản ánh đúng trạng thái hiện tại.
        $this->connection->transaction(function () use ($conversationId, $messages, $table): void {
            $this->connection->delete(
                sprintf('DELETE FROM %s WHERE conversation_id = ?', $table),
                [$conversationId],
            );

            foreach (array_values($messages) as $position => $message) {
                $this->connection->insert(
                    sprintf(
                        'INSERT INTO %s (id, conversation_id, position, role, payload) VALUES (?, ?, ?, ?, ?)',
                        $table,
                    ),
                    [
                        $this->nextId(),
                        $conversationId,
                        $position,
                        $message->role()->value,
                        json_encode($message->toArray(), JSON_UNESCAPED_UNICODE),
                    ],
                );
            }
        });
    }

    public function delete(string $conversationId): void
    {
        $this->connection->delete(
            sprintf('DELETE FROM %s WHERE conversation_id = ?', $this->table()),
            [$conversationId],
        );
    }

    protected function nextId(): string
    {
        // Dùng Snowflake nếu ứng dụng đã khởi động, không thì lùi về uniqid
        // để repository vẫn chạy được trong test.
        if (function_exists('app')) {
            try {
                return (string) app('snowflake')->id();
            } catch (\Throwable) {
                // Chưa có container - rơi xuống nhánh dưới.
            }
        }

        return (string) hexdec(substr(uniqid('', true), 0, 12));
    }

    protected function table(): string
    {
        return (string) ($this->config['table'] ?? 'ai_chat_messages');
    }
}
