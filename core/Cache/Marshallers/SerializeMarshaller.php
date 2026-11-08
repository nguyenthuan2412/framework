<?php

namespace Ryra\Core\Cache\Marshallers;

class SerializeMarshaller implements MarshallerInterface
{
    private bool $useIgbinarySerialize = false;

    public function __construct()
    {
        if (
            \extension_loaded('igbinary')
            && \version_compare('3.1.6', phpversion('igbinary'), '<=')
        ) {
            $this->useIgbinarySerialize = true;
        }
    }

    public function marshall(mixed $value): mixed
    {
        return $this->useIgbinarySerialize
            ? \igbinary_serialize($value)
            : \serialize($value);
    }

    public function unmarshall(string $value): mixed
    {
        if ('b:0;' === $value) {
            return false;
        }

        if ('N;' === $value) {
            return null;
        }

        return $this->useIgbinarySerialize
            ? \igbinary_unserialize($value)
            : \unserialize($value);
    }
}