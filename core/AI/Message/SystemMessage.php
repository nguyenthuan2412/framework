<?php

namespace Ryra\Core\AI\Message;

class SystemMessage extends Message
{
    public function __construct(
        string $content = '',
        /**
         * Đánh dấu block này để nhà cung cấp cache lại (Anthropic prompt caching).
         * Chỉ có tác dụng với driver hỗ trợ, driver khác sẽ bỏ qua.
         */
        public readonly bool $cacheable = false,
    ) {
        parent::__construct($content);
    }

    public function role(): Role
    {
        return Role::System;
    }

    public function cache(bool $cacheable = true): static
    {
        return new static($this->content, $cacheable);
    }

    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'cacheable' => $this->cacheable,
        ];
    }
}
