<?php

namespace Ryra\Core\Excel\Exceptions;

class ConcernConflictException extends ExcelException
{
    public static function queryOrCollectionAndChunk(): self
    {
        return new self(
            'Cannot use FromQuery or FromCollection together with WithChunkReading on import. Use WithChunkReading only with file imports.'
        );
    }
}
