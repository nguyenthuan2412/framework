<?php

namespace Ryra\Core\Messenger\Transport\AMQP;

class AMQPConfig
{
    public function __construct(private array $config)
    {
    }

    public function getHost()
    {

    }

    public function getQueueConfig(string $name): array
    {
        return [];
    }
}