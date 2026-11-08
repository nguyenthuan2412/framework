<?php

namespace Ryra\Core\Pagination;

class Cursor
{
    /**
     * @param array<string, mixed> $parameters Giá trị của các cột dùng để order (keyed theo alias).
     * @param bool $pointsToNextItems Con trỏ đang trỏ tới trang kế tiếp (true) hay trang trước (false).
     */
    public function __construct(
        protected array $parameters = [],
        protected bool $pointsToNextItems = true
    ) {}

    public function parameter(string $column): mixed
    {
        return $this->parameters[$column] ?? null;
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function pointsToNextItems(): bool
    {
        return $this->pointsToNextItems;
    }

    public function pointsToPreviousItems(): bool
    {
        return !$this->pointsToNextItems;
    }

    /**
     * Mã hoá cursor thành chuỗi base64 an toàn cho URL.
     */
    public function encode(): string
    {
        $payload = json_encode(array_merge($this->parameters, [
            '_pointsToNextItems' => $this->pointsToNextItems,
        ]));

        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    }

    /**
     * Giải mã chuỗi cursor. Trả về null nếu chuỗi rỗng hoặc không hợp lệ.
     */
    public static function fromEncoded(?string $encoded): ?static
    {
        if (empty($encoded)) {
            return null;
        }

        $decoded = base64_decode(str_replace(['-', '_'], ['+', '/'], $encoded), true);
        if ($decoded === false) {
            return null;
        }

        $parameters = json_decode($decoded, true);
        if (!is_array($parameters) || !array_key_exists('_pointsToNextItems', $parameters)) {
            return null;
        }

        $pointsToNextItems = (bool) $parameters['_pointsToNextItems'];
        unset($parameters['_pointsToNextItems']);

        return new static($parameters, $pointsToNextItems);
    }

    public function __toString(): string
    {
        return $this->encode();
    }
}
