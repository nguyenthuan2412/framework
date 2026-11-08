<?php

namespace Ryra\Core\Collection;

use Ryra\Core\Macro\Macroable;
use InvalidArgumentException;
use ArgumentCountError;
use ArrayAccess;
use Closure;
use Countable;

class Arr
{
    use Macroable;

    /**
     * Determine whether the given value is array accessible.
     */
    public static function accessible(mixed $value): bool
    {
        return \is_array($value) || $value instanceof ArrayAccess;
    }

    /**
     * Get an item from an array using 'dot' notation.
     */
    public static function get(ArrayAccess|array $array, string|int $key, mixed $default = null): mixed
    {
        if (\array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (!\str_contains($key, '.')) {
            return self::getValue($default);
        }

        foreach (self::explodeKey($key) as $segment) {
            if (isset($array[$segment])) {
                $array = $array[$segment];
            } else {
                return self::getValue($default);
            }
        }
        return $array;
    }

    /**
     * Check if an item or items exist in an array using "dot" notation.
     */
    public static function has(ArrayAccess|array $array, string|array $keys): bool
    {
        $keys = (array) $keys;
        if (!$array || $keys === []) {
            return false;
        }
        foreach ($keys as $key) {
            $subKeyArray = $array;

            if (static::exists($array, $key)) {
                continue;
            }

            foreach (\explode('.', $key) as $segment) {
                if (static::accessible($subKeyArray) && static::exists($subKeyArray, $segment)) {
                    $subKeyArray = $subKeyArray[$segment];
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Push an item into the end of array.
     */
    public static function push(ArrayAccess|array &$array, mixed $value): void
    {
        $array[] = $value;
    }

    /**
     * Push an item onto the beginning of an array.
     */
    public static function prepend(array &$array, mixed $value, string|int|null $key = null): void
    {
        if (\is_null($key)) {
            \array_unshift($array, $value);
        } else {
            $array = [$key => $value] + $array;
        }
    }

    /**
     * Set an array item to a given value using "dot" notation.
     *
     * If no key is given to the method, the entire array will be replaced.
     */
    public static function set(array &$array, string|int|null $key, mixed $value): array
    {
        if (\is_null($key)) {
            return $array = $value;
        }

        $keys = \explode('.', $key);

        foreach ($keys as $i => $key) {
            if (\count($keys) === 1) {
                break;
            }
            unset($keys[$i]);

            if (!isset($array[$key]) || !\is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[\array_shift($keys)] = $value;
        return $array;
    }

    /**
     * Remove one or many array items from a given array using "dot" notation.
     */
    public static function forget(ArrayAccess|array &$array, array $keys): void
    {
        foreach ($keys as $key) {
            if (\array_key_exists($key, $array)) {
                unset($array[$key]);
            }

            if (\str_contains($key, '.')) {
                $value = null;
                $segments = self::explodeKey($key);
                if (!isset($array[$segments[0]])) {
                    return;
                }
                $value = &$array[$segments[0]];
                unset($segments[0]);
                $lastkey = \end($segments);
                foreach ($segments as $segment) {
                    if (!isset($value[$segment])) {
                        break;
                    }
                    if ($segment !== $lastkey) {
                        $value = &$value[$segment];
                    }
                }
                unset($value[$lastkey]);
            }
        }
    }

    /**
     * Get all of the given array except for a specified array of keys.
     */
    public static function except(ArrayAccess|array $array, array $keys): array
    {
        static::forget($array, $keys);
        return $array;
    }

    /**
     * Get and remove item from the array.
     */
    public static function pull(ArrayAccess|array &$array, string|int $key, mixed $default = null): mixed
    {
        $value = static::get($array, $key, $default);
        static::forget($array, [$key]);
        return $value;
    }

    /**
     * Return all the keys or a subset of the keys of an array.
     */
    public static function keys(iterable $array): array
    {
        $array = \iterator_to_list($array);
        return \array_keys($array);
    }

    /**
     * Return all the values of an array.
     */
    public static function values(iterable $array): array
    {
        $array = \iterator_to_list($array);
        return \array_values($array);
    }

    /**
     * Check key in array.
     */
    public static function exists(ArrayAccess|array $array, string|int $key): bool
    {
        return isset($array[$key]);
    }

    /** Count elements in array.
     */
    public static function count(Countable|iterable $items): int
    {
        if (!$items instanceof Countable) {
            $items = \iterator_to_list($items);
        }
        return \count($items);
    }

    /**
     * Count the number of items in the collection by a field or using a callback.
     */
    public static function countBy(iterable $array, ?callable $callback = null): array
    {
        $array = \iterator_to_list($array);
        if ($callback !== null) {
            $array = Arr::map($callback, $array);
        }
        return \array_count_values($array);
    }

    /**
     * Computes the difference of arrays.
     */
    public static function diff(iterable ...$arrays): array
    {
        $arrays = Arr::map(static fn (iterable $array) => \iterator_to_list($array), $arrays);
        return \array_diff(...$arrays);
    }

    /**
     * Computes the difference of arrays with additional index check.
     */
    public static function diffAssoc(iterable ...$arrays): array
    {
        $arrays = Arr::map(static fn (iterable $array) => \iterator_to_list($array), $arrays);
        return \array_diff_assoc(...$arrays);
    }

    /**
     * Computes the difference of arrays using keys for comparison.
     */
    public static function diffKeys(iterable ...$arrays): array
    {
        $arrays = Arr::map(static fn (iterable $array) => \iterator_to_list($array), $arrays);
        return \array_diff_key(...$arrays);
    }

    /**
     * Check array is empty.
     */
    public static function empty(iterable $array): bool
    {
        $array = \iterator_to_list($array);
        return empty($array);
    }

    /**
     * Pad array to the specified length with a value.
     */
    public static function pad(iterable $array, int $size, mixed $value): array
    {
        return \array_pad(\iterator_to_list($array), $size, $value);
    }

    /**
     * Pick one or more random keys out of an array.
     */
    public static function randomKey(iterable $array, int $num = 1): array|string|int
    {
        return \array_rand(\iterator_to_list($array), $num);
    }

    /**
     * Pick one or more random values out of an array.
     */
    public static function random(iterable $array, int $num = 1, bool $preserveKeys = false): mixed
    {
        $array = \iterator_to_list($array);

        $count = \count($array);
        if ($num > $count) {
            throw new InvalidArgumentException(
                "You requested {$num} items, but there are only {$count} items available."
            );
        }

        $rand = \array_rand($array, $num);
        if (\is_array($rand)) {
            $array = \array_filter($array, static fn ($value, $key) => \in_array($key, $rand), ARRAY_FILTER_USE_BOTH);
            return $preserveKeys ? \array_values($array) : $array;
        }
        return $array[$rand];
    }

    /**
     * Replace the array items with the given items.
     */
    public static function replace(iterable ...$arrays): array
    {
        $arrays = Arr::map(static fn (iterable $array) => \iterator_to_list($array), $arrays);
        return \array_replace(...$arrays);
    }

    /**
     * Recursively replace the array items with the given items.
     */
    public static function replaceRecursive(iterable ...$arrays): array
    {
        $arrays = Arr::map(static fn (iterable $array) => \iterator_to_list($array), $arrays);
        return \array_replace_recursive(...$arrays);
    }

    /**
     * Get the items with the specified keys.
     */
    public static function only(iterable $array, array $keys): array
    {
        $array = \iterator_to_list($array);
        return \array_intersect_key($array, \array_flip($keys));
    }

    /**
     * Find highest value.
     */
    public static function max(mixed ...$items): mixed
    {
        return \max(...$items);
    }

    /**
     * Find lowest value.
     */
    public static function min(mixed ...$items): mixed
    {
        return \min(...$items);
    }

    /**
     * Applies the callback to the elements of the given arrays.
     */
    public static function map(callable $callback, array $array): array
    {
        $keys = \array_keys($array);
        try {
            $items = \array_map($callback, $array, $keys);
        } catch (ArgumentCountError) {
            $items = \array_map($callback, $array);
        }
        return \array_combine($keys, $items);
    }

    /**
     * Merge one or more items.
     */
    public static function merge(iterable ...$items): array
    {
        foreach ($items as &$item) {
            $item = \iterator_to_list($item);
        }
        unset($item);
        return (array) \array_merge(...$items);
    }

    /**
     * Merge one or more items recursively.
     */
    public static function mergeRecursive(iterable ...$items): array
    {
        foreach ($items as &$item) {
            $item = \iterator_to_list($item);
        }
        unset($item);
        return (array) \array_merge_recursive(...$items);
    }

    /**
     * Pluck an array of values from an array.
     */
    public static function pluck(iterable $array, string|int $value, string|int|null $key = null): array
    {
        list($value, $key) = self::explodeKeys($value, $key);
        $results = [];
        foreach ($array as $item) {
            $itemValue = \extract_item($item, $value);
            if (\is_null($key)) {
                if ($itemValue !== $item) {
                    $results[] = $itemValue;
                }
            } else {
                $itemKey = \extract_item($item, $key);
                if ($itemValue !== $item && $itemKey !== $item) {
                    if (\is_object($itemKey) && \method_exists($itemKey, '__toString')) {
                        $itemKey = (string) $itemKey;
                    }
                    $results[$itemKey] = $itemValue;
                }
            }
        }
        return $results;
    }

    /**
     * Removes duplicate values from an array.
     */
    public static function unique(iterable $array, int $flags = SORT_STRING): array
    {
        $array = \iterator_to_list($array);
        return \array_unique($array, $flags);
    }

    /**
     * Get first value by callback in an array.
     */
    public static function first(iterable $array, ?callable $callback = null, mixed $default = null): mixed
    {
        $array = \iterator_to_list($array);
        if (\is_null($callback)) {
            if (empty($array)) {
                return $default;
            }

            foreach ($array as $value) {
                return $value;
            }
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return self::getValue($default);
    }

    /**
     * Get last value by callback in an array.
     */
    public static function last(iterable $array, ?callable $callback = null, mixed $default = null): mixed
    {
        $array = \iterator_to_list($array);
        if (\is_null($callback)) {
            return empty($array) ? $default : \end($array);
        }

        foreach (\array_reverse($array) as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return self::getValue($default);
    }

    /**
     * Sort the array.
     */
    public static function sort(iterable &$array, bool $descending = false, int $options = SORT_REGULAR): bool
    {
        $array = \iterator_to_list($array);
        return $descending ? \rsort($array, $options) : \sort($array, $options);
    }

    /**
     * Sort the array keys.
     */
    public static function sortKeys(array &$array, bool $descending = false, int $options = SORT_REGULAR): bool
    {
        return $descending ? \krsort($array, $options) : \ksort($array, $options);
    }

    /**
     * Searches the array for a given value and returns the first corresponding key if successful.
     */
    public static function search(mixed $value, iterable $item, bool $strict = false): string|int|false
    {
        $item = \iterator_to_list($item);
        return \array_search($value, $item, $strict);
    }

    /**
     * Exchanges all keys with their associated values in an array.
     */
    public static function flip(iterable $item): array
    {
        return \array_flip(\iterator_to_list($item));
    }

    /**
     * Generate URL-encoded query string.
     */
    public static function query(iterable $item): string
    {
        return \http_build_query(\iterator_to_list($item), '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Concatenate values of a given key as a string.
     */
    public static function implode(string $glue, iterable $item): string
    {
        $item = \iterator_to_list($item);
        return \implode($glue, $item);
    }

    /**
     * Computes the intersection of arrays.
     */
    public static function intersect(iterable ...$arrays): array
    {
        $arrays = Arr::map(static fn (iterable $array) => \iterator_to_list($array), $arrays);
        return \array_intersect(...$arrays);
    }

    public static function where(array $array, callable $callback): array
    {
        return \array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    public static function whereNotNull(array $array): array
    {
        return self::where($array, static fn ($value) => $value !== null);
    }

    public static function whereNotEmpty(array $array): array
    {
        return static::where($array, static fn ($value) => !empty($value));
    }

    public static function whereNotBlank(array $array): array
    {
        return static::where($array, static fn ($value) => !empty(trim($value)));
    }

    /**
     * Flatten a multi-dimensional array into a single level.
     */
    public static function flatten(iterable $array, int $depth = PHP_INT_MAX): array
    {
        $result = [];
        foreach ($array as $item) {
            $item = $item instanceof Collection ? $item->all() : $item;
            if (!\is_array($item)) {
                $result[] = $item;
            } else {
                $item = $depth === 1 ? \array_values($item) : self::flatten($item, $depth - 1);
                $result = \array_merge($result, $item);
            }
        }
        return $result;
    }

    /**
     * Collapse an array of arrays into a single array.
     */
    public static function collapse(iterable $array): array
    {
        $results = [];

        foreach ($array as $values) {
            $values = \iterator_to_list($values);
            if (\is_array($values)) {
                $results[] = $values;
            }
        }

        return \array_merge([], ...$results);
    }

    /**
     * Creates an array by using one array for keys and another for its values.
     */
    public static function combine(iterable $keys, iterable $values): array
    {
        $keys = \iterator_to_list($keys);
        $values = \iterator_to_list($values);
        return \array_combine($keys, $values);
    }

    /**
     * Shuffle an array.
     */
    public static function shuffle(array $array, ?int $seed = null): array
    {
        if (\is_null($seed)) {
            \shuffle($array);
        } else {
            \mt_srand($seed);
            \shuffle($array);
            \mt_srand();
        }

        return $array;
    }

    /**
     * Extract a slice of the array.
     */
    public static function slice(array $array, int $start, ?int $length = null, bool $preserveKeys = false): array
    {
        return \array_slice($array, $start, $length, $preserveKeys);
    }

    /**
     * If the given value is not an array and not null, wrap it in one.
     */
    public static function wrap(mixed $value): array
    {
        if (\is_null($value)) {
            return [];
        }
        return \is_array($value) ? $value : [$value];
    }

    protected static function getValue(mixed $value): mixed
    {
        return $value instanceof Closure ? $value() : $value;
    }

    protected static function explodeKeys(string|int|null ...$keys): array
    {
        return Arr::map(static function ($key) {
            if ($key !== null) {
                $key = self::explodeKey($key);
            }
            return $key;
        }, $keys);
    }

    private static function explodeKey(string|int $key): array
    {
        return \explode('.', $key);
    }
}