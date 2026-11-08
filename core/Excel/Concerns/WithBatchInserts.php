<?php

namespace Ryra\Core\Excel\Concerns;

interface WithBatchInserts
{
    /**
     * Number of model instances to collect before flushing to the database.
     * Works together with ToModel — models are saved in batches of this size.
     * Reduces the number of database round-trips for large imports.
     */
    public function batchSize(): int;
}
