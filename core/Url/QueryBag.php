<?php

namespace Ryra\Core\Url;

use Ryra\Core\Support\Arrayable;

final class QueryBag implements \IteratorAggregate, \Countable, \Stringable, \ArrayAccess, Arrayable, \JsonSerializable
{
    public const ENC_RFC3986 = \PHP_QUERY_RFC3986;
    public const ENC_RFC1738 = \PHP_QUERY_RFC1738;

    private readonly array $parameters;
    private ?string $cached = null;

    public function __construct(array $parameters = [])
    {
        $this->parameters = $parameters;
    }

    public static function fromString(string $query): self
    {
        if (\str_starts_with($query, '?')) {
            $query = \substr($query, 1);
        }

        if ($query === '') {
            return new self();
        }

        \parse_str($query, $result);

        return new self($result);
    }

    public static function fromArray(array $parameters): self
    {
        return new self($parameters);
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->parameters);
    }

    public function all(): array
    {
        return $this->parameters;
    }

    public function keys(): array
    {
        return \array_keys($this->parameters);
    }

    public function isEmpty(): bool
    {
        return empty($this->parameters);
    }

    public function isNotEmpty(): bool
    {
        return !empty($this->parameters);
    }

    // =========================================================================
    // Immutable Modification
    // =========================================================================

    public function with(string $key, mixed $value): self
    {
        $params = $this->parameters;
        $params[$key] = $value;

        return new self($params);
    }

    public function withMany(array $params): self
    {
        return new self(\array_merge($this->parameters, $params));
    }

    public function without(string|array $keys): self
    {
        $keys = (array) $keys;
        $params = $this->parameters;

        foreach ($keys as $key) {
            unset($params[$key]);
        }

        return new self($params);
    }

    public function only(string|array $keys): self
    {
        $keys = (array) $keys;

        return new self(\array_intersect_key($this->parameters, \array_flip($keys)));
    }

    public function merge(array|self $other): self
    {
        $other = $other instanceof self ? $other->all() : $other;

        return new self(\array_merge($this->parameters, $other));
    }

    public function filter(callable $callback): self
    {
        return new self(\array_filter($this->parameters, $callback, \ARRAY_FILTER_USE_BOTH));
    }

    public function map(callable $callback): self
    {
        return new self(\array_map($callback, $this->parameters));
    }

    // =========================================================================
    // Output
    // =========================================================================

    public function toString(int $encoding = self::ENC_RFC3986, string $separator = '&'): string
    {
        if ($this->cached !== null && $encoding === self::ENC_RFC3986 && $separator === '&') {
            return $this->cached;
        }

        $result = \http_build_query($this->parameters, '', $separator, $encoding);

        if ($encoding === self::ENC_RFC3986 && $separator === '&') {
            $this->cached = $result;
        }

        return $result;
    }

    public function toArray(): array
    {
        return $this->parameters;
    }

    public function jsonSerialize(): array
    {
        return $this->parameters;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    // =========================================================================
    // Interfaces
    // =========================================================================

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->parameters);
    }

    public function count(): int
    {
        return \count($this->parameters);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('QueryBag is immutable. Use with() to create a new instance.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('QueryBag is immutable. Use without() to create a new instance.');
    }
}
