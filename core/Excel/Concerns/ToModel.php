<?php

namespace Ryra\Core\Excel\Concerns;

interface ToModel
{
    /**
     * Transform a single imported row into a model instance and return it.
     * Return null to skip saving this row.
     * The model will be collected and saved according to WithBatchInserts settings.
     */
    public function model(array $row): mixed;
}
