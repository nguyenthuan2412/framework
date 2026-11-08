<?php

namespace Ryra\Core\AI\Response;

use JsonException;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Message\AssistantMessage;
use Ryra\Core\Support\Arrayable;
use Stringable;

class ChatResponse implements Arrayable, Stringable
{
    /**
     * @param array<int, ToolCall>  $toolCalls
     * @param array<string, mixed>  $raw Payload gốc của nhà cung cấp, dùng khi cần dữ liệu đặc thù.
     */
    public function __construct(
        public readonly string $text = '',
        public readonly array $toolCalls = [],
        public readonly Usage $usage = new Usage(),
        public readonly FinishReason $finishReason = FinishReason::Unknown,
        public readonly string $model = '',
        public readonly string $provider = '',
        public readonly array $raw = [],
    ) {}

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    public function isRefusal(): bool
    {
        return $this->finishReason === FinishReason::Refusal;
    }

    /**
     * Giải mã phản hồi dạng JSON (dùng với structured output).
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $text = trim($this->text);

        // Vài model vẫn bọc JSON trong markdown fence dù đã yêu cầu structured output.
        if (str_starts_with($text, '```')) {
            $text = trim(preg_replace('/^```(?:json)?|```$/m', '', $text) ?? '');
        }

        try {
            $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new AIException(
                sprintf('Response is not valid JSON: %s', $e->getMessage()),
                previous: $e,
            );
        }

        return is_array($decoded) ? $decoded : ['value' => $decoded];
    }

    public function toMessage(): AssistantMessage
    {
        return new AssistantMessage($this->text, $this->toolCalls);
    }

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'tool_calls' => array_map(static fn (ToolCall $call): array => $call->toArray(), $this->toolCalls),
            'usage' => $this->usage->toArray(),
            'finish_reason' => $this->finishReason->value,
            'model' => $this->model,
            'provider' => $this->provider,
        ];
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
