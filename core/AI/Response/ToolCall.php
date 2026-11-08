<?php

namespace Ryra\Core\AI\Response;

use Ryra\Core\Support\Arrayable;

class ToolCall implements Arrayable
{
    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $metadata Dữ liệu riêng của nhà cung cấp phải
     *                                       gửi trả nguyên vẹn ở lượt sau, ví dụ
     *                                       thoughtSignature của Gemini 3.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments = [],
        public readonly array $metadata = [],
    ) {}

    public function argument(string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'name' => $this->name,
            'arguments' => $this->arguments,
            'metadata' => $this->metadata ?: null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
