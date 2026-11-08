<?php

namespace Ryra\Core\Excel\Concerns;

interface WithMappedCells
{
    /**
     * Return a map of field name => cell address for non-tabular (form-like) imports.
     *
     * Example:
     *   return [
     *       'company_name' => 'B2',
     *       'date'         => 'B4',
     *       'total'        => 'D10',
     *   ];
     *
     * @return array<string, string>
     */
    public function mapping(): array;

    /**
     * Receive the mapped field => value array built from the cell mapping.
     */
    public function model(array $data): mixed;
}
