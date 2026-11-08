<?php

namespace Ryra\Core\Messenger\Serialization;

interface SerializerInterface
{
    public function decode(string $message): mixed;

    public function encode(mixed $message): string;
}