<?php

namespace Ryra\Core\Excel\Concerns;

/**
 * Marker interface.
 * When implemented, PhpSpreadsheet will pre-calculate all formula cell values before writing.
 * This increases accuracy but may slow down large exports with many formulas.
 */
interface ShouldPreCalcFormulas
{
}
