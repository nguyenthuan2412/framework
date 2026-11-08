<?php

namespace Ryra\Core\Cache\Drivers;

use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;
use Ryra\Core\Cache\Marshallers\MarshallerInterface;

abstract class AbstractDriver implements CacheInterface
{
    public function __construct(
        protected MarshallerInterface $marshaller,
        protected ?string $prefix = null,
    ) {}

    public function remember(string $key, int|\DateInterval $ttl, callable $callback): mixed
    {
        $value = $this->get($key);

        if (!\is_null($value)) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    protected function getKey(string $key): string
    {
        return $this->prefix . $key;
    }

    protected function ttlToInt(null|int|\DateInterval $ttl = null): int
    {
        if (\is_int($ttl)) {
            return $ttl;
        }

        if ($ttl instanceof \DateInterval) {
            $reference = new DateTimeImmutable();
            $end = $reference->add($ttl);
            return $end->getTimestamp() - $reference->getTimestamp();
        }

        return 0;
    }
}