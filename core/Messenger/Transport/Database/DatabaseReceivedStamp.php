<?php

namespace Ryra\Core\Messenger\Transport\Database;

use Ryra\Core\Messenger\Stamp\StampInterface;

class DatabaseReceivedStamp implements StampInterface
{
    public function __construct(private string $id) {}

    public function getId(): string
    {
        return $this->id;
    }
}