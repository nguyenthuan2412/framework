<?php

namespace Ryra\Core\Support;

trait EnumUtil
{
    public static function in(mixed $value): bool
    {
        return static::tryFrom($value) !== null;
    }

    public static function keyExists(string $key): bool
    {
        return in_array($key, static::keys());
    }

    public static function values(): array
    {
        return array_column(static::cases(), 'value');
    }

    public static function keys(): array
    {
        return array_column(static::cases(), 'name');
    }

    public static function toArray(): array
    {
        return array_combine(static::keys(), static::values());
    }

    public static function search(mixed $value, $strict = false): string|false
    {
        if (!is_callable($value)) {
            if ($strict) {
                $value = fn ($item) => $item === $value;
            } else {
                $value = fn ($item) => $item == $value;
            }
        }

        foreach (static::cases() as $case) {
            if ($value($case->value, $case->name)) {
                return $case->name;
            }
        }

        return false;
    }

    public static function toJson(): string
    {
        return json_encode(static::toArray());
    }
}