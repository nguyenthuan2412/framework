<?php

namespace Ryra\Core\AI\Message;

use Ryra\Core\AI\Response\ToolCall;

class AssistantMessage extends Message
{
    /**
     * @param array<int, ToolCall> $toolCalls
     */
    public function __construct(
        string $content = '',
        public readonly array $toolCalls = [],
    ) {
        parent::__construct($content);
    }

    public function role(): Role
    {
        return Role::Assistant;
    }

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    public function isEmpty(): bool
    {
        return parent::isEmpty() && !$this->hasToolCalls();
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'tool_calls' => array_map(static fn (ToolCall $call): array => $call->toArray(), $this->toolCalls),
        ];
    }
}
