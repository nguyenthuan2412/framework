<?php

namespace Ryra\Core\AI\Message;

class ToolResultMessage extends Message
{
    public function __construct(
        public readonly string $toolCallId,
        public readonly string $name,
        string $content = '',
        public readonly bool $isError = false,
    ) {
        parent::__construct($content);
    }

    public function role(): Role
    {
        return Role::Tool;
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'tool_call_id' => $this->toolCallId,
            'name' => $this->name,
            'is_error' => $this->isError,
        ];
    }
}
