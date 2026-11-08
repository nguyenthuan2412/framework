<?php

declare(strict_types=1);

namespace Ryra\Core\Database\Migration\Attributes;

class ColumnType
{
    const STRING = 'string';
    const TEXT = 'text';
    const LONGTEXT = 'longtext';
    const INT = 'int';
    const BIGINT = 'bigint';
    const SMALLINT = 'smallint';
    const BOOLEAN = 'boolean';
    const FLOAT = 'float';
    const DECIMAL = 'decimal';
    const DATETIME = 'datetime';
    const DATE = 'date';
    const JSON = 'json';
    const UUID = 'uuid';
}