<?php

namespace Ryra\Core\Datastar;

use Ryra\Core\Datastar\Enums\ElementPatchMode;

/**
 * Một mảnh giao diện mà server có thể render riêng.
 *
 * Mỗi fragment ứng với một `{% block %}` trong view. Client khai báo cần mảnh
 * nào (qua header `Datastar-Fragments`), server chỉ render đúng những mảnh đó
 * nên không tốn công query lẫn băng thông cho phần không đổi.
 */
final class Fragment
{
    /**
     * @param string $name Tên block trong view
     * @param string|null $selector Selector đích, null thì dựa vào id của element trong block
     * @param ElementPatchMode|string|null $mode Cách patch, null là `outer`
     * @param \Closure|array<string, mixed> $context Dữ liệu riêng của fragment, closure sẽ
     *        chỉ được gọi khi fragment thực sự được render
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $selector = null,
        public readonly ElementPatchMode|string|null $mode = null,
        public readonly \Closure|array $context = [],
    ) {}

    /**
     * Dựng fragment từ nhiều dạng khai báo cho gọn ở controller:
     *
     * ```php
     * 'content'                                            // chỉ tên
     * 'todo_list' => '#todo-list'                          // kèm selector
     * 'todo_list' => fn () => ['todos' => Todo::all()]     // kèm dữ liệu riêng
     * 'todo_list' => ['selector' => '#todo-list', 'mode' => 'inner', 'context' => [...]]
     * ```
     */
    public static function make(string $name, mixed $definition = null): self
    {
        if ($definition instanceof self) {
            return $definition;
        }

        if ($definition === null) {
            return new self($name);
        }

        if (\is_string($definition)) {
            return new self($name, selector: $definition);
        }

        if ($definition instanceof \Closure) {
            return new self($name, context: $definition);
        }

        if (\is_array($definition)) {
            return new self(
                name: $definition['name'] ?? $name,
                selector: $definition['selector'] ?? null,
                mode: $definition['mode'] ?? null,
                context: $definition['context'] ?? [],
            );
        }

        throw new \InvalidArgumentException(
            \sprintf('Khai báo fragment "%s" không hợp lệ, chỉ nhận string|Closure|array|Fragment.', $name)
        );
    }

    /**
     * Chuẩn hoá cả danh sách khai báo về mảng `[tên => Fragment]`.
     *
     * @param string|array<int|string, mixed> $fragments
     * @return array<string, self>
     */
    public static function collect(string|array $fragments): array
    {
        $collection = [];

        foreach ((array) $fragments as $key => $definition) {
            $fragment = \is_int($key)
                ? self::make(\is_string($definition) ? $definition : (string) ($definition['name'] ?? ''), \is_string($definition) ? null : $definition)
                : self::make($key, $definition);

            $collection[$fragment->name] = $fragment;
        }

        return $collection;
    }

    /**
     * Đọc danh sách fragment mà client yêu cầu.
     *
     * Chấp nhận `a,b`, `a b` hoặc JSON `["a","b"]`.
     *
     * @return string[]
     */
    public static function parseRequested(?string $raw): array
    {
        if ($raw === null || \trim($raw) === '') {
            return [];
        }

        $raw = \trim($raw);

        if (\str_starts_with($raw, '[') && \json_validate($raw)) {
            $names = \json_decode($raw, true);

            return \is_array($names) ? \array_values(\array_filter(\array_map('strval', $names))) : [];
        }

        return \array_values(\array_filter(\array_map('trim', \preg_split('/[,\s]+/', $raw))));
    }

    /**
     * Dữ liệu riêng của fragment, closure chỉ được gọi ở đây.
     *
     * @param array<string, mixed> $shared
     * @return array<string, mixed>
     */
    public function context(array $shared = []): array
    {
        $context = $this->context instanceof \Closure ? ($this->context)($shared) : $this->context;

        return \array_merge($shared, (array) $context);
    }
}
