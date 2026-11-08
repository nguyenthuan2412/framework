<?php

namespace Ryra\Core\MongoDB;

use MongoDB\Client;

class Connection
{

    protected Client $connection;

    public function __construct(protected array $config)
    {

    }

    protected function createConnection(string $dns, array $options): Client
    {
        // return 
    }
}