<?php

namespace Ryra\Core\Cache\Drivers;

use Ryra\Core\Cache\Marshallers\MarshallerInterface;
use Ryra\Core\Redis\RedisManager;

class RedisDriver extends AbstractDriver
{
    public function __construct(
        protected RedisManager $redis,
        protected string $connection,
        MarshallerInterface $marshaller,
        ?string $prefix = null,
    ) {
        parent::__construct($marshaller, $prefix);
    }

    /**
     * Lấy Redis connection.
     */
    protected function connection(): object
    {
        return $this->redis->connection($this->connection);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->connection()->get($this->getKey($key));

        if ($value === false || $value === null) {
            return $default;
        }

        return $this->marshaller->unmarshall($value);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $key = $this->getKey($key);
        $value = $this->marshaller->marshall($value);
        $ttl = $this->ttlToInt($ttl);

        if ($ttl > 0) {
            return (bool) $this->connection()->setex($key, $ttl, $value);
        }

        return (bool) $this->connection()->set($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return (bool) $this->connection()->del($this->getKey($key));
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $connection = $this->connection();
        $pattern = $this->prefix ? $this->getKey('*') : '*';

        $iterator = null;
        while ($keys = $connection->scan($iterator, $pattern, 1000)) {
            $connection->del(...$keys);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $prefixedKeys = [];
        $originalKeys = [];

        foreach ($keys as $key) {
            $originalKeys[] = $key;
            $prefixedKeys[] = $this->getKey($key);
        }

        $values = $this->connection()->mget($prefixedKeys);
        $result = [];

        foreach ($originalKeys as $index => $key) {
            $value = $values[$index] ?? false;

            if ($value === false || $value === null) {
                $result[$key] = $default;
            } else {
                $result[$key] = $this->marshaller->unmarshall($value);
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        $ttl = $this->ttlToInt($ttl);
        $connection = $this->connection();

        if ($ttl <= 0) {
            $pairs = [];
            foreach ($values as $key => $value) {
                $pairs[$this->getKey($key)] = $this->marshaller->marshall($value);
            }
            return (bool) $connection->mset($pairs);
        }

        // Với TTL, phải set từng key
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $prefixedKeys = [];

        foreach ($keys as $key) {
            $prefixedKeys[] = $this->getKey($key);
        }

        if (empty($prefixedKeys)) {
            return true;
        }

        return (bool) $this->connection()->del(...$prefixedKeys);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return (bool) $this->connection()->exists($this->getKey($key));
    }

    /**
     * Tăng giá trị của key.
     */
    public function increment(string $key, int $value = 1): int|false
    {
        return $this->connection()->incrby($this->getKey($key), $value);
    }

    /**
     * Giảm giá trị của key.
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->connection()->decrby($this->getKey($key), $value);
    }

    /**
     * Lưu giá trị vĩnh viễn (không có TTL).
     */
    public function forever(string $key, mixed $value): bool
    {
        return $this->set($key, $value, 0);
    }

    /**
     * Xóa key theo pattern.
     */
    public function flush(string $pattern = '*'): bool
    {
        $connection = $this->connection();
        $pattern = $this->getKey($pattern);

        $iterator = null;
        while ($keys = $connection->scan($iterator, $pattern, 1000)) {
            $connection->del(...$keys);
        }

        return true;
    }
}
