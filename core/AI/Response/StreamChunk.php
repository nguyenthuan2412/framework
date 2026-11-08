<?php

namespace Ryra\Core\AI\Response;

use Ryra\Core\Support\Arrayable;
use Stringable;

class StreamChunk implements Arrayable, Stringable
{
    /**
     * @param array<int, ToolCall> $toolCalls Chỉ có ở chunk cuối, khi model yêu cầu gọi tool.
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $text = '',
        public readonly bool $isLast = false,
        public readonly array $toolCalls = [],
        public readonly ?Usage $usage = null,
        public readonly ?FinishReason $finishReason = null,
        public readonly array $raw = [],
    ) {}

    public static function delta(string $text, array $raw = []): static
    {
        return new static($text, raw: $raw);
    }

    public static function last(
        array $toolCalls = [],
        ?Usage $usage = null,
        ?FinishReason $finishReason = null,
        array $raw = [],
    ): static {
        return new static('', true, $toolCalls, $usage, $finishReason, $raw);
    }

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'is_last' => $this->isLast,
            'tool_calls' => array_map(static fn (ToolCall $call): array => $call->toArray(), $this->toolCalls),
            'usage' => $this->usage?->toArray(),
            'finish_reason' => $this->finishReason?->value,
        ];
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
