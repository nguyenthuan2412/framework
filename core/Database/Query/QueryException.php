<?php

namespace Ryra\Core\Database\Query;

use PDOException;
use Throwable;

class QueryException extends PDOException
{
    /**
     * Create a new query exception instance.
     */
    public function __construct(protected string $sql, protected array $bindings, Throwable $previous)
    {
        parent::__construct('', 0, $previous);
        $this->code = $previous->getCode();
        $this->message = $this->formatMessage($sql, $bindings, $previous);

        if ($previous instanceof PDOException) {
            $this->errorInfo = $previous->errorInfo;
        }
    }

    /**
     * Format the SQL error message.
     */
    protected function formatMessage(string $sql, array $bindings, Throwable $previous): string
    {
        $newstring = $sql;
        foreach ($bindings as $binding) {
            $pos = strpos($sql, '?');
            if ($pos !== false) {
                $newstring = substr_replace($sql, $binding, $pos, 1);
            }
        }
        return "{$previous->getMessage()} (SQL: $newstring)";
    }

    /**
     * Get the SQL for the query.
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Get the bindings for the query.
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}