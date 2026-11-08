<?php

namespace Ryra\Core\Messenger\Serialization;

class PhpSerializer implements SerializerInterface
{
    public function decode(string $message): mixed
    {
        return unserialize($message);
    }

    public function encode(mixed $message): string
    {
        return serialize($message);
    }
}