<?php

namespace Ryra\Core\Excel\Exceptions;

class NoResultsException extends ExcelException
{
    public function __construct()
    {
        parent::__construct('No results found for the given export.');
    }
}
