<?php

namespace Ryra\Core\Messenger\Stamp;

class TransportMessageIdStamp implements StampInterface
{
    public function __construct(private mixed $id) {}

    public function getId(): mixed
    {
        return $this->id;
    }
}