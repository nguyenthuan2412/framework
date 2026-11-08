<?php

namespace Ryra\Core\Excel\Concerns;

/**
 * Marker interface. Implementing this will auto-size all columns after writing.
 * Note: auto-sizing can be slow on very wide sheets; prefer WithColumnWidths for performance.
 */
interface ShouldAutoSize
{
}
