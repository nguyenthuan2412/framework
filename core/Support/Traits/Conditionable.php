<?php

namespace Ryra\Core\Support\Traits;

trait Conditionable
{
    /**
     * Apply the callback if the given "value" is truthy.
     */
    public function when(mixed $value, callable $callback, ?callable $default = null): mixed
    {
        if ($value) {
            return $callback($this, $value) ?: $this;
        } elseif ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }

    /**
     * Apply the callback if the given "value" is falsy.
     */
    public function unless(mixed $value, callable $callback, ?callable $default = null): mixed
    {
        if (!$value) {
            return $callback($this, $value) ?: $this;
        } elseif ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }
}