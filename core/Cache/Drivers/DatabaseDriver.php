<?php

namespace Ryra\Core\Cache\Drivers;

use Ryra\Core\Cache\Marshallers\MarshallerInterface;
use Ryra\Core\Database\Connection;
use Ryra\Core\Database\DB;
use DateInterval;

class DatabaseDriver extends AbstractDriver
{
    protected static bool $booted = false;

    public function __construct(
        protected Connection $connection,
        protected string $table,
        MarshallerInterface $marshaller,
        ?string $prefix = null,
    ) {
        parent::__construct($marshaller, $prefix);
        $this->ensureCacheTableExists();
    }

    protected function ensureCacheTableExists(): void
    {
        if (static::$booted) {
            return;
        }

        $hasTable = $this->connection
            ->query()
            ->from(DB::raw('information_schema.tables'))
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $this->connection->getPrefix() . $this->table)
            ->exists();

        if (!$hasTable) {
            $this->createCacheTable();
        }

        static::$booted = true;
    }

    protected function createCacheTable(): void
    {
        $driver = $this->connection->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $table = $this->connection->getPrefix() . $this->table;

        $sql = match ($driver) {
            'mysql' => "
                CREATE TABLE `{$table}` (
                    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
                    `item` LONGBLOB NOT NULL,
                    `lifetime` INT NOT NULL DEFAULT 0,
                    `time` INT NOT NULL DEFAULT 0
                ) ENGINE=InnoDB
            ",
            'pgsql' => "
                CREATE TABLE \"{$table}\" (
                    \"key\" VARCHAR(255) NOT NULL PRIMARY KEY,
                    \"item\" BYTEA NOT NULL,
                    \"lifetime\" INT NOT NULL DEFAULT 0,
                    \"time\" INT NOT NULL DEFAULT 0
                )
            ",
            'sqlite' => "
                CREATE TABLE \"{$table}\" (
                    \"key\" VARCHAR(255) NOT NULL PRIMARY KEY,
                    \"item\" BLOB NOT NULL,
                    \"lifetime\" INT NOT NULL DEFAULT 0,
                    \"time\" INT NOT NULL DEFAULT 0
                )
            ",
            default => throw new \RuntimeException("Unsupported database driver: {$driver}"),
        };

        $this->connection->statement($sql);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->connection
            ->query()
            ->table($this->table)
            ->where('key', $this->getKey($key))
            ->first();

        if (!empty($item)) {
            // lifetime = 0 nghĩa là forever, không cần check expiry
            if ($item['lifetime'] === 0 || \time() < $item['lifetime'] + $item['time']) {
                return $this->marshaller->unmarshall($item['item']);
            }
            $this->delete($key);
        }

        return $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        return $this->connection
            ->query()
            ->table($this->table)
            ->updateOrInsert(
                ['key' => $this->getKey($key)],
                [
                    'item' => $this->marshaller->marshall($value),
                    'lifetime' => $this->ttlToInt($ttl),
                    'time' => \time(),
                ]
            );
    }

    public function delete(string $key): bool
    {
        return (bool) $this->connection
            ->query()
            ->table($this->table)
            ->where('key', $this->getKey($key))
            ->delete();
    }

    public function clear(): bool
    {
        return (bool) $this->connection
            ->query()
            ->table($this->table)
            ->truncate();
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $keysList = iterator_to_list($keys);
        $prefixedKeys = \array_map([$this, 'getKey'], $keysList);

        $items = $this->connection
            ->query()
            ->table($this->table)
            ->whereIn('key', $prefixedKeys)
            ->get();

        // Map prefixed key → item
        $itemMap = [];
        foreach ($items as $item) {
            $itemMap[$item['key']] = $item;
        }

        $results = [];
        foreach ($keysList as $key) {
            $prefixedKey = $this->getKey($key);

            if (!isset($itemMap[$prefixedKey])) {
                $results[$key] = $default;
                continue;
            }

            $item = $itemMap[$prefixedKey];

            // Check TTL: lifetime = 0 means forever
            if ($item['lifetime'] === 0 || \time() < $item['lifetime'] + $item['time']) {
                $results[$key] = $this->marshaller->unmarshall($item['item']);
            } else {
                $this->delete($key);
                $results[$key] = $default;
            }
        }

        return $results;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        return $this->connection->transaction(function () use (&$values, $ttl) {
            foreach ($values as $key => $value) {
                $this->set($key, $value, $ttl);
            }
            return true;
        });
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $keys = \array_map([$this, 'getKey'], iterator_to_list($keys));

        return (bool) $this->connection
            ->query()
            ->table($this->table)
            ->whereIn('key', $keys)
            ->delete();
    }

    public function has(string $key): bool
    {
        $item = $this->connection
            ->query()
            ->table($this->table)
            ->where('key', $this->getKey($key))
            ->first();

        if (empty($item)) {
            return false;
        }

        // Check TTL: expired thì xóa và trả false
        if ($item['lifetime'] !== 0 && \time() >= $item['lifetime'] + $item['time']) {
            $this->delete($key);
            return false;
        }

        return true;
    }
}
