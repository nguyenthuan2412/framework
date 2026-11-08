<?php

namespace Ryra\Core\AI\Http;

class SseEvent
{
    public function __construct(
        public readonly ?string $name,
        public readonly string $data,
    ) {}

    /**
     * OpenAI báo hiệu kết thúc stream bằng "data: [DONE]".
     */
    public function isDone(): bool
    {
        return trim($this->data) === '[DONE]';
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $decoded = json_decode($this->data, true);

        return is_array($decoded) ? $decoded : [];
    }
}
