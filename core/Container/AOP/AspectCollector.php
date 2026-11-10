<?php

namespace Ryra\Core\Container\AOP;

/**
 * Lưu danh sách aspect và pointcut. Chỉ dùng lúc sinh proxy (matching được tính một lần
 * rồi ghi thẳng vào code proxy), runtime không chạy qua đây.
 */
class AspectCollector
{
    /**
     * @var array<class-string, array{classes: string[], attributes: string[], priority: int, order: int}>
     */
    private array $aspects = [];

    /**
     * Tăng mỗi khi danh sách aspect thay đổi, để AopProxyManager biết mà xoá cache.
     */
    private int $revision = 0;

    /**
     * Đăng ký aspect. Pointcut lấy từ #[Aspect] trên class và gộp với tham số truyền vào.
     *
     * @param class-string<AroundInterface> $aspect
     * @param string[] $classes
     * @param class-string[] $attributes
     */
    public function add(string $aspect, array $classes = [], array $attributes = [], ?int $priority = null): static
    {
        if (!\is_subclass_of($aspect, AroundInterface::class)) {
            throw new \InvalidArgumentException("Aspect {$aspect} must implement " . AroundInterface::class);
        }

        $meta = null;
        foreach ((new \ReflectionClass($aspect))->getAttributes(Aspect::class) as $attribute) {
            $meta = $attribute->newInstance();
        }

        $this->aspects[$aspect] = [
            'classes' => \array_values(\array_unique([...($meta?->classes ?? []), ...$classes])),
            'attributes' => \array_values(\array_unique(\array_map(
                static fn(string $a) => \ltrim($a, '\\'),
                [...($meta?->attributes ?? []), ...$attributes],
            ))),
            'priority' => $priority ?? $meta?->priority ?? 0,
            'order' => $this->aspects[$aspect]['order'] ?? \count($this->aspects),
        ];
        $this->revision++;

        return $this;
    }

    public function remove(string $aspect): static
    {
        unset($this->aspects[$aspect]);
        $this->revision++;
        return $this;
    }

    public function clear(): static
    {
        $this->aspects = [];
        $this->revision++;
        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->aspects === [];
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    /**
     * Có aspect nào có thể khớp class này không (lọc nhanh trước khi dùng Reflection).
     */
    public function mayMatchClass(string $class): bool
    {
        foreach ($this->aspects as $meta) {
            if ($meta['attributes'] !== []) {
                return true;
            }
            foreach ($meta['classes'] as $pattern) {
                if ($this->match(\explode('::', $pattern, 2)[0], $class)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Aspect áp dụng cho method, đã sắp theo thứ tự chạy (priority giảm dần, rồi thứ tự đăng ký).
     *
     * @param string[] $methodAttributes tên class các attribute gắn trên method.
     * @return class-string<AroundInterface>[]
     */
    public function aspectsFor(string $class, string $method, array $methodAttributes = []): array
    {
        $matched = [];
        foreach ($this->aspects as $aspect => $meta) {
            if ($this->matchMethod($meta, $class, $method, $methodAttributes)) {
                $matched[$aspect] = $meta;
            }
        }

        \uasort($matched, static fn(array $a, array $b) => [$b['priority'], $a['order']] <=> [$a['priority'], $b['order']]);

        return \array_keys($matched);
    }

    private function matchMethod(array $meta, string $class, string $method, array $methodAttributes): bool
    {
        foreach ($meta['classes'] as $pattern) {
            [$classPattern, $methodPattern] = \explode('::', $pattern, 2) + [1 => '*'];
            if ($this->match($classPattern, $class) && $this->match($methodPattern, $method)) {
                return true;
            }
        }

        foreach ($methodAttributes as $attribute) {
            if (\in_array(\ltrim($attribute, '\\'), $meta['attributes'], true)) {
                return true;
            }
        }

        return false;
    }

    private function match(string $pattern, string $subject): bool
    {
        $pattern = \ltrim($pattern, '\\');
        if (!\str_contains($pattern, '*')) {
            return \strcasecmp($pattern, $subject) === 0;
        }

        $regex = '/^' . \str_replace('\*', '.*', \preg_quote($pattern, '/')) . '$/i';
        return (bool) \preg_match($regex, $subject);
    }
}
