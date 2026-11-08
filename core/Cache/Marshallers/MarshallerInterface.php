<?php

namespace Ryra\Core\Cache\Marshallers;

interface MarshallerInterface
{
    /**
     * Serializes a list of values.
     */
    public function marshall(mixed $value): mixed;

    /**
     * Unserializes a single value and throws an exception if anything goes wrong.
     *
     * @throws \Exception Whenever unserialization fails
     */
    public function unmarshall(string $value): mixed;
}