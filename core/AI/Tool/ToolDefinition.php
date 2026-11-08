<?php

namespace Ryra\Core\AI\Tool;

use Closure;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\Support\Arrayable;
use stdClass;

class ToolDefinition implements Arrayable
{
    public const EMPTY_SCHEMA = [
        'type' => 'object',
        'properties' => [],
        'required' => [],
    ];

    /**
     * @param array<string, mixed> $parameters JSON Schema mô tả tham số đầu vào.
     * @param Closure|null         $handler    Hàm thực thi phía server; null nghĩa là client tự xử lý.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters = self::EMPTY_SCHEMA,
        public readonly ?Closure $handler = null,
        /**
         * Bắt nhà cung cấp validate tham số đúng schema (OpenAI/Anthropic strict mode).
         */
        public readonly bool $strict = false,
    ) {
        if ($name === '') {
            throw new AIException('Tool name cannot be empty.');
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public static function make(
        string $name,
        string $description,
        array $parameters = self::EMPTY_SCHEMA,
        ?callable $handler = null,
        bool $strict = false,
    ): static {
        return new static(
            $name,
            $description,
            $parameters,
            $handler === null ? null : Closure::fromCallable($handler),
            $strict,
        );
    }

    public function isExecutable(): bool
    {
        return $this->handler !== null;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function execute(array $arguments): mixed
    {
        if ($this->handler === null) {
            throw new AIException(sprintf('Tool "%s" has no handler to execute.', $this->name));
        }

        return ($this->handler)($arguments);
    }

    /**
     * Schema đã chuẩn hoá: luôn có type/properties/required để mọi nhà cung cấp đều nhận.
     *
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        $schema = $this->parameters;
        $schema['type'] ??= 'object';
        $schema['properties'] ??= [];
        $schema['required'] ??= [];

        // Tool không có tham số: mảng rỗng của PHP mã hoá thành "[]" nhưng JSON
        // Schema yêu cầu object "{}". Gemini từ chối thẳng, nên phải ép kiểu.
        if ($schema['properties'] === []) {
            $schema['properties'] = new stdClass();
        }

        if ($this->strict) {
            $schema['additionalProperties'] = false;
        }

        return $schema;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->schema(),
            'strict' => $this->strict,
        ];
    }
}
