<?php

namespace Ryra\Core\AI\Advisor;

use Ryra\Core\AI\ChatOptions;
use Ryra\Core\AI\Message\Message;
use Ryra\Core\AI\Message\SystemMessage;
use Ryra\Core\AI\Message\UserMessage;
use Ryra\Core\AI\Prompt;

/**
 * Yêu cầu đang trên đường đi tới model. Advisor được phép sửa trực tiếp.
 */
class ChatRequest
{
    /**
     * @param array<string, mixed> $context Chỗ để advisor truyền dữ liệu cho nhau.
     */
    public function __construct(
        public Prompt $prompt,
        public ChatOptions $options,
        public array $context = [],
    ) {}

    /**
     * Câu hỏi mới nhất của người dùng - thứ mà advisor RAG cần để đi tìm tài liệu.
     */
    public function lastUserMessage(): ?UserMessage
    {
        foreach (array_reverse($this->prompt->messages()) as $message) {
            if ($message instanceof UserMessage) {
                return $message;
            }
        }

        return null;
    }

    /**
     * Chèn chỉ dẫn hệ thống lên đầu, trước mọi lượt hội thoại.
     */
    public function prependSystem(string $content): static
    {
        return $this->replaceMessages([
            new SystemMessage($content),
            ...$this->prompt->messages(),
        ]);
    }

    /**
     * Chèn lịch sử vào giữa: sau phần system, trước lượt nói hiện tại.
     *
     * @param array<int, Message> $history
     */
    public function prependHistory(array $history): static
    {
        if ($history === []) {
            return $this;
        }

        return $this->replaceMessages([
            ...$this->prompt->systemMessages(),
            ...$history,
            ...$this->prompt->conversation(),
        ]);
    }

    /**
     * @param array<int, Message> $messages
     */
    public function replaceMessages(array $messages): static
    {
        $this->prompt = new Prompt($messages);

        return $this;
    }

    public function withContext(string $key, mixed $value): static
    {
        $this->context[$key] = $value;

        return $this;
    }

    public function context(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }
}
