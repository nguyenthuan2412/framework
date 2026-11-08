<?php

namespace Ryra\Core\Excel\Concerns;

interface WithBackgroundColor
{
    /**
     * Return an ARGB hex color string to use as the default background for all cells.
     *
     * Example: 'FFFFFFFF' for white, 'FFD9EAD3' for light green.
     */
    public function backgroundColor(): string;
}
