<?php

namespace Ryra\Core\Messenger\Transport\Database;

class DatabaseConfig
{
    public function __construct()
    {

    }

    public function getConnectionName(): string
    {
        return 'database';
    }
}