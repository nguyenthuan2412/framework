<?php

namespace Ryra\Core\AI;

use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Tool\ToolDefinition;
use Ryra\Core\AI\Tool\Toolbox;
use Ryra\Core\Support\Arrayable;

class ChatOptions implements Arrayable
{
    public const EFFORTS = ['low', 'medium', 'high', 'xhigh', 'max'];

    protected ?string $model = null;

    protected ?int $maxTokens = null;

    protected ?float $temperature = null;

    protected ?float $topP = null;

    /**
     * @var array<int, string>|null
     */
    protected ?array $stopSequences = null;

    protected ?Toolbox $toolbox = null;

    /**
     * auto | any | none | tên tool cụ thể
     */
    protected ?string $toolChoice = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $responseSchema = null;

    protected string $responseSchemaName = 'response';

    /**
     * Mức độ "suy nghĩ" của model: low | medium | high | xhigh | max.
     * Driver nào không hỗ trợ sẽ bỏ qua.
     */
    protected ?string $effort = null;

    protected ?bool $thinking = null;

    /**
     * Tham số thô, ghi đè trực tiếp vào payload của nhà cung cấp.
     *
     * @var array<string, mixed>
     */
    protected array $extra = [];

    public static function make(): static
    {
        return new static();
    }

    public function model(?string $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function maxTokens(?int $maxTokens): static
    {
        if ($maxTokens !== null && $maxTokens < 1) {
            throw new AIException("maxTokens must be greater than 0, {$maxTokens} given.");
        }

        $this->maxTokens = $maxTokens;
        return $this;
    }

    public function getMaxTokens(): ?int
    {
        return $this->maxTokens;
    }

    public function temperature(?float $temperature): static
    {
        if ($temperature !== null && ($temperature < 0 || $temperature > 2)) {
            throw new AIException("temperature must be between 0 and 2, {$temperature} given.");
        }

        $this->temperature = $temperature;
        return $this;
    }

    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    public function topP(?float $topP): static
    {
        $this->topP = $topP;
        return $this;
    }

    public function getTopP(): ?float
    {
        return $this->topP;
    }

    /**
     * @param array<int, string>|null $stopSequences
     */
    public function stopSequences(?array $stopSequences): static
    {
        $this->stopSequences = $stopSequences;
        return $this;
    }

    /**
     * @return array<int, string>|null
     */
    public function getStopSequences(): ?array
    {
        return $this->stopSequences;
    }

    /**
     * @param array<int, ToolDefinition|object>|Toolbox|null $tools
     */
    public function tools(Toolbox|array|null $tools): static
    {
        $this->toolbox = match (true) {
            $tools === null => null,
            $tools instanceof Toolbox => $tools,
            default => new Toolbox($tools),
        };

        return $this;
    }

    public function getTools(): ?Toolbox
    {
        return $this->toolbox;
    }

    public function toolChoice(?string $toolChoice): static
    {
        $this->toolChoice = $toolChoice;
        return $this;
    }

    public function getToolChoice(): ?string
    {
        return $this->toolChoice;
    }

    /**
     * @param array<string, mixed>|null $schema JSON Schema mà phản hồi phải tuân theo.
     */
    public function responseSchema(?array $schema, string $name = 'response'): static
    {
        $this->responseSchema = $schema;
        $this->responseSchemaName = $name;
        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResponseSchema(): ?array
    {
        return $this->responseSchema;
    }

    public function getResponseSchemaName(): string
    {
        return $this->responseSchemaName;
    }

    public function effort(?string $effort): static
    {
        if ($effort !== null && !in_array($effort, static::EFFORTS, true)) {
            throw new AIException(
                sprintf('Unsupported effort "%s". Allowed: %s.', $effort, implode(', ', static::EFFORTS))
            );
        }

        $this->effort = $effort;
        return $this;
    }

    public function getEffort(): ?string
    {
        return $this->effort;
    }

    public function thinking(?bool $thinking): static
    {
        $this->thinking = $thinking;
        return $this;
    }

    public function getThinking(): ?bool
    {
        return $this->thinking;
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function extra(array $extra): static
    {
        $this->extra = array_merge($this->extra, $extra);
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    public function hasTools(): bool
    {
        return $this->toolbox !== null && !$this->toolbox->isEmpty();
    }

    public function isStructured(): bool
    {
        return $this->responseSchema !== null;
    }

    /**
     * Lấp giá trị còn thiếu bằng bộ options mặc định của driver.
     * Giá trị đã set ở đây luôn được ưu tiên.
     */
    public function withDefaults(?ChatOptions $defaults): static
    {
        if ($defaults === null) {
            return $this;
        }

        $this->model ??= $defaults->model;
        $this->maxTokens ??= $defaults->maxTokens;
        $this->temperature ??= $defaults->temperature;
        $this->topP ??= $defaults->topP;
        $this->stopSequences ??= $defaults->stopSequences;
        $this->toolbox ??= $defaults->toolbox;
        $this->toolChoice ??= $defaults->toolChoice;
        $this->responseSchema ??= $defaults->responseSchema;
        $this->effort ??= $defaults->effort;
        $this->thinking ??= $defaults->thinking;
        $this->extra = array_merge($defaults->extra, $this->extra);

        return $this;
    }

    public function __clone(): void
    {
        if ($this->toolbox !== null) {
            $this->toolbox = clone $this->toolbox;
        }
    }

    public function toArray(): array
    {
        return array_filter([
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'top_p' => $this->topP,
            'stop_sequences' => $this->stopSequences,
            'tools' => $this->toolbox?->toArray(),
            'tool_choice' => $this->toolChoice,
            'response_schema' => $this->responseSchema,
            'effort' => $this->effort,
            'thinking' => $this->thinking,
            'extra' => $this->extra ?: null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
