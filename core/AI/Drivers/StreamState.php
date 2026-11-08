<?php

namespace Ryra\Core\AI\Drivers;

use Ryra\Core\AI\Response\FinishReason;
use Ryra\Core\AI\Response\ToolCall;
use Ryra\Core\AI\Response\Usage;

/**
 * Trạng thái tích luỹ trong lúc stream.
 *
 * Tham số của tool được nhà cung cấp gửi thành nhiều mảnh JSON rời rạc,
 * phải gom đủ mới decode được - đó là lý do cần giữ state xuyên suốt stream.
 */
class StreamState
{
    /**
     * @var array<int|string, array{id: string, name: string, arguments: string, metadata: array}>
     */
    protected array $pendingToolCalls = [];

    protected string $text = '';

    protected ?FinishReason $finishReason = null;

    protected Usage $usage;

    public function __construct()
    {
        $this->usage = new Usage();
    }

    public function appendText(string $text): static
    {
        $this->text .= $text;
        return $this;
    }

    public function text(): string
    {
        return $this->text;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function openToolCall(int|string $index, string $id, string $name, array $metadata = []): static
    {
        $this->pendingToolCalls[$index] = [
            'id' => $id,
            'name' => $name,
            'arguments' => '',
            'metadata' => $metadata,
        ];

        return $this;
    }

    public function appendToolArguments(int|string $index, string $partial): static
    {
        if (!isset($this->pendingToolCalls[$index])) {
            $this->openToolCall($index, '', '');
        }

        $this->pendingToolCalls[$index]['arguments'] .= $partial;
        return $this;
    }

    /**
     * Bổ sung thông tin đến muộn (OpenAI gửi id/name ở chunk đầu, có thể thiếu).
     */
    public function fillToolCall(int|string $index, ?string $id = null, ?string $name = null): static
    {
        if (!isset($this->pendingToolCalls[$index])) {
            $this->openToolCall($index, $id ?? '', $name ?? '');
            return $this;
        }

        if ($id !== null && $id !== '') {
            $this->pendingToolCalls[$index]['id'] = $id;
        }

        if ($name !== null && $name !== '') {
            $this->pendingToolCalls[$index]['name'] = $name;
        }

        return $this;
    }

    public function usage(Usage $usage): static
    {
        $this->usage = $this->usage->add($usage);
        return $this;
    }

    public function getUsage(): Usage
    {
        return $this->usage;
    }

    public function finishReason(FinishReason $reason): static
    {
        $this->finishReason = $reason;
        return $this;
    }

    public function getFinishReason(): FinishReason
    {
        return $this->finishReason ?? FinishReason::Unknown;
    }

    /**
     * @return array<int, ToolCall>
     */
    public function toolCalls(): array
    {
        $calls = [];

        foreach ($this->pendingToolCalls as $pending) {
            if ($pending['name'] === '') {
                continue;
            }

            $arguments = json_decode($pending['arguments'] === '' ? '{}' : $pending['arguments'], true);

            $calls[] = new ToolCall(
                $pending['id'],
                $pending['name'],
                is_array($arguments) ? $arguments : [],
                $pending['metadata'] ?? [],
            );
        }

        return $calls;
    }

    public function hasToolCalls(): bool
    {
        return $this->pendingToolCalls !== [];
    }
}
