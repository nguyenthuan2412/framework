<?php

namespace Ryra\Core\Collection;

use Ryra\Core\Support\Arrayable;
use IteratorAggregate;
use JsonSerializable;
use Countable;

interface Enumerable extends IteratorAggregate, JsonSerializable, Countable, Arrayable
{
    /**
     * Create a new collection instance.
     * 
     * @param mixed $items
     * @return static
     */
    public static function make(mixed $items = []): static;

    /**
     * Get all of the items in the collection.
     * 
     * @return array
     */
    public function all(): array;

    /**
     * Get all keys in a collection.
     * 
     * @return static
     */
    public function keys(): static;

    /**
     * Get all values in a collection.
     * 
     * @return static
     */
    public function values(): static;

    /**
     * Create a collection with the given range.
     */
    public static function range(string|int $start, string|int $end, string|int $step = 1): static;
}