<?php

use Ryra\Core\Collection\Collection;
use Ryra\Core\Collection\Enumerable;
use Ryra\Core\Support\Arrayable;

if (!function_exists('collect')) {
    function collect(mixed $items = []): Collection {
        return new Collection($items);
    }
}

if (!function_exists('extract_item')) {
    function extract_item(iterable $array, array $segments): mixed {
        $result = $array;
        $tmp = null;
        foreach ($array as $key => $item) {
            if ($key == $segments[0]) {
                if (is_array($item) && count($segments) > 1) {
                    $tmp = extract_item($item, array_slice($segments, 1));
                    $result = $item === $tmp ? $result : $tmp;
                } else {
                    $result = $item;
                }
                break;
            }
        }
        return $result;
    }
}

if (!function_exists('iterator_to_list')) {
    function iterator_to_list(iterable $items): array {
        if (is_array($items)) {
            return $items;
        } else if ($items instanceof Arrayable) {
            return $items->toArray();
        } else if ($items instanceof \Traversable) {
            return iterator_to_array($items);
        } else if ($items instanceof \JsonSerializable) {
            return (array) $items->jsonSerialize();
        } else if ($items instanceof \UnitEnum) {
            return [$items];
        }
        return (array) $items;
    }
}
