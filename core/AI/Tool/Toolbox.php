<?php

namespace Ryra\Core\AI\Tool;

use Countable;
use IteratorAggregate;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Message\ToolResultMessage;
use Ryra\Core\AI\Response\ToolCall;
use Ryra\Core\Support\Arrayable;
use Throwable;
use Traversable;

class Toolbox implements Arrayable, Countable, IteratorAggregate
{
    /**
     * @var array<string, ToolDefinition>
     */
    protected array $tools = [];

    /**
     * @param array<int, ToolDefinition|object> $tools
     */
    public function __construct(array $tools = [])
    {
        $this->add(...$tools);
    }

    /**
     * Thêm tool. Nhận cả ToolDefinition lẫn object có method gắn #[Tool].
     *
     * @param ToolDefinition|object ...$tools
     */
    public function add(object ...$tools): static
    {
        foreach ($tools as $tool) {
            if ($tool instanceof ToolDefinition) {
                $this->tools[$tool->name] = $tool;
                continue;
            }

            foreach (ToolFactory::fromObject($tool) as $definition) {
                $this->tools[$definition->name] = $definition;
            }
        }

        return $this;
    }

    public function get(string $name): ?ToolDefinition
    {
        return $this->tools[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * @return array<int, ToolDefinition>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    public function isEmpty(): bool
    {
        return $this->tools === [];
    }

    /**
     * Có ít nhất một tool chạy được ở phía server (dùng để bật vòng lặp tool tự động).
     */
    public function hasExecutable(): bool
    {
        foreach ($this->tools as $tool) {
            if ($tool->isExecutable()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Chạy một lời gọi tool và bọc kết quả thành message gửi ngược lại cho model.
     * Lỗi được trả về dưới dạng tool result có is_error để model tự xử lý,
     * thay vì ném exception làm đứt vòng hội thoại.
     */
    public function execute(ToolCall $call): ToolResultMessage
    {
        $tool = $this->get($call->name);

        if ($tool === null) {
            return new ToolResultMessage(
                $call->id,
                $call->name,
                sprintf('Tool "%s" is not registered.', $call->name),
                true,
            );
        }

        if (!$tool->isExecutable()) {
            throw new AIException(sprintf('Tool "%s" must be executed by the client.', $call->name));
        }

        try {
            return new ToolResultMessage($call->id, $call->name, $this->stringify($tool->execute($call->arguments)));
        } catch (Throwable $e) {
            return new ToolResultMessage($call->id, $call->name, $e->getMessage(), true);
        }
    }

    protected function stringify(mixed $result): string
    {
        if (is_string($result)) {
            return $result;
        }

        if ($result instanceof Arrayable) {
            $result = $result->toArray();
        }

        if ($result === null) {
            return '';
        }

        if (is_scalar($result)) {
            return (string) $result;
        }

        return (string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function count(): int
    {
        return count($this->tools);
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->all());
    }

    public function toArray(): array
    {
        return array_map(static fn (ToolDefinition $tool): array => $tool->toArray(), $this->all());
    }
}
