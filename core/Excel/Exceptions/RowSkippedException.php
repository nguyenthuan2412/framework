<?php

namespace Ryra\Core\Excel\Exceptions;

use Ryra\Core\Excel\Validators\Failure;

class RowSkippedException extends ExcelException
{
    /** @param Failure[] $failures */
    public function __construct(private readonly array $failures)
    {
        parent::__construct('Row was skipped due to validation failures.');
    }

    /** @return Failure[] */
    public function failures(): array
    {
        return $this->failures;
    }

    public function skippedRows(): array
    {
        return array_unique(array_map(fn (Failure $f) => $f->row(), $this->failures));
    }
}
