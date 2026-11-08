<?php

namespace Ryra\Core\AI\Message;

use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Response\ToolCall;

/**
 * Dựng lại Message từ mảng - dùng khi đọc lịch sử hội thoại đã lưu.
 */
class MessageFactory
{
    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): Message
    {
        $role = Role::tryFrom((string) ($data['role'] ?? ''));

        if ($role === null) {
            throw new AIException(sprintf('Unknown message role "%s".', $data['role'] ?? ''));
        }

        $content = (string) ($data['content'] ?? '');

        return match ($role) {
            Role::System => new SystemMessage($content, (bool) ($data['cacheable'] ?? false)),
            Role::User => new UserMessage($content, static::media($data['media'] ?? [])),
            Role::Assistant => new AssistantMessage($content, static::toolCalls($data['tool_calls'] ?? [])),
            Role::Tool => new ToolResultMessage(
                (string) ($data['tool_call_id'] ?? ''),
                (string) ($data['name'] ?? ''),
                $content,
                (bool) ($data['is_error'] ?? false),
            ),
        };
    }

    /**
     * @param  array<int, array<string, mixed>> $rows
     * @return array<int, Message>
     */
    public static function fromArrayList(array $rows): array
    {
        return array_map(static fn (array $row): Message => static::fromArray($row), $rows);
    }

    /**
     * @param  array<int, array<string, mixed>> $rows
     * @return array<int, ToolCall>
     */
    protected static function toolCalls(array $rows): array
    {
        return array_map(
            static fn (array $row): ToolCall => new ToolCall(
                (string) ($row['id'] ?? ''),
                (string) ($row['name'] ?? ''),
                (array) ($row['arguments'] ?? []),
                (array) ($row['metadata'] ?? []),
            ),
            $rows,
        );
    }

    /**
     * @param  array<int, array<string, mixed>> $rows
     * @return array<int, Media>
     */
    protected static function media(array $rows): array
    {
        return array_map(
            static fn (array $row): Media => new Media(
                (string) ($row['mime_type'] ?? ''),
                (string) ($row['data'] ?? ''),
            ),
            $rows,
        );
    }
}
