<?php

namespace Ryra\Core\Support;

trait Conditionable
{
    public function when(mixed $value, callable $callback, ?callable $default = null)
    {
        if ($value) {
            return $callback($this, $value) ?? $this;
        } else if ($default) {
            return $default($this, $value) ?? $this;
        }

        return $this;
    }

    public function unless(mixed $value, callable $callback, ?callable $default = null)
    {
        return $this->when(!$value, $callback, $default);
    }
}