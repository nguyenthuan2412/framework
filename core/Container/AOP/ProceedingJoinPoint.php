<?php

namespace Ryra\Core\Container\AOP;

/**
 * Điểm cắt đang chạy: chứa thông tin method bị gọi và chuỗi aspect còn lại.
 *
 * Không dùng Reflection: tham số đã được proxy sinh sẵn gom thành mảng tên => giá trị,
 * method gốc là closure gọi thẳng parent::method().
 */
final class ProceedingJoinPoint
{
    /**
     * @var array<class-string, AroundInterface> aspect đã khởi tạo, dùng chung cho cả worker.
     */
    private static array $resolved = [];

    private int $index = 0;

    /**
     * @param array<string, mixed> $arguments tên tham số => giá trị (tham số variadic là mảng).
     * @param \Closure(array): mixed $original
     * @param class-string<AroundInterface>[] $aspects
     */
    public function __construct(
        public readonly object $instance,
        public readonly string $className,
        public readonly string $methodName,
        public array $arguments,
        private \Closure $original,
        private array $aspects,
    ) {}

    /**
     * Gọi từ code proxy sinh sẵn.
     */
    public static function run(
        object $instance,
        string $className,
        string $methodName,
        array $arguments,
        \Closure $original,
        array $aspects,
    ): mixed {
        return (new self($instance, $className, $methodName, $arguments, $original, $aspects))->process();
    }

    /**
     * Chạy aspect kế tiếp, hết aspect thì chạy method gốc với $this->arguments hiện tại.
     */
    public function process(): mixed
    {
        if (!isset($this->aspects[$this->index])) {
            return ($this->original)($this->arguments);
        }

        $aspect = self::$resolved[$class = $this->aspects[$this->index]] ??= self::resolve($class);
        $next = clone $this;
        $next->index++;

        return $aspect->process($next);
    }

    /**
     * Bỏ qua các aspect còn lại, gọi thẳng method gốc.
     */
    public function processOriginalMethod(): mixed
    {
        return ($this->original)($this->arguments);
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getArgument(string $name, mixed $default = null): mixed
    {
        return \array_key_exists($name, $this->arguments) ? $this->arguments[$name] : $default;
    }

    public function setArgument(string $name, mixed $value): void
    {
        if (!\array_key_exists($name, $this->arguments)) {
            throw new \InvalidArgumentException("Method {$this->className}::{$this->methodName}() has no argument \${$name}");
        }
        $this->arguments[$name] = $value;
    }

    /**
     * Xoá các instance aspect đã cache (dùng trong test).
     */
    public static function flushAspectInstances(): void
    {
        self::$resolved = [];
    }

    private static function resolve(string $class): AroundInterface
    {
        $container = \Ryra\Core\Container\Container::getInstance();
        $aspect = $container->has($class) ? $container->get($class) : \Ryra\Core\Container\Container::make($class);
        if (!$aspect instanceof AroundInterface) {
            throw new \LogicException("Aspect {$class} must implement " . AroundInterface::class);
        }

        return $aspect;
    }
}
