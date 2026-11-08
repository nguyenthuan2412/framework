<?php

namespace Ryra\Core\AI\Response;

use Ryra\Core\Support\Arrayable;

class Usage implements Arrayable
{
    public function __construct(
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        /** Token đọc được từ cache - rẻ hơn nhiều so với input thường. */
        public readonly int $cacheReadTokens = 0,
        /** Token ghi vào cache - đắt hơn input thường một chút. */
        public readonly int $cacheWriteTokens = 0,
    ) {}

    public function totalTokens(): int
    {
        return $this->inputTokens
            + $this->outputTokens
            + $this->cacheReadTokens
            + $this->cacheWriteTokens;
    }

    public function add(?Usage $other): static
    {
        if ($other === null) {
            return $this;
        }

        return new static(
            $this->inputTokens + $other->inputTokens,
            $this->outputTokens + $other->outputTokens,
            $this->cacheReadTokens + $other->cacheReadTokens,
            $this->cacheWriteTokens + $other->cacheWriteTokens,
        );
    }

    public function toArray(): array
    {
        return [
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cache_read_tokens' => $this->cacheReadTokens,
            'cache_write_tokens' => $this->cacheWriteTokens,
            'total_tokens' => $this->totalTokens(),
        ];
    }
}
