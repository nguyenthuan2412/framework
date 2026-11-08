<?php

namespace Ryra\Core\Excel\Concerns;

interface WithCustomCsvSettings
{
    /**
     * Return CSV settings to override the defaults.
     *
     * Supported keys for export (Writer\Csv):
     *   delimiter, enclosure, line_ending, use_bom, excel_compatibility, include_separator_line
     *
     * Supported keys for import (Reader\Csv):
     *   delimiter, enclosure, escape_character, input_encoding, contiguous
     *
     * @return array<string, mixed>
     */
    public function getCsvSettings(): array;
}
