<?php

namespace Ryra\Core\RateLimit\Drivers;

use Ryra\Core\Database\Connection;
use Ryra\Core\Database\DB;
use Ryra\Core\RateLimit\Contracts\RateLimiterDriverInterface;

/**
 * Database driver — works with any SQL database.
 * Best for: projects without Redis, persistence across restarts.
 * Note: slower than Redis; recommend adding index on `key` column.
 */
class DatabaseDriver implements RateLimiterDriverInterface
{
    protected static bool $booted = false;

    public function __construct(
        private Connection $connection,
        private string $table = 'rate_limits',
        private string $prefix = 'rl:',
    ) {
        $this->ensureTableExists();
    }

    private function ensureTableExists(): void
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
            $this->createTable();
        }

        static::$booted = true;
    }

    private function createTable(): void
    {
        $table = $this->connection->getPrefix() . $this->table;

        $this->connection->statement("
            CREATE TABLE `{$table}` (
                `key`        VARCHAR(255) NOT NULL PRIMARY KEY,
                `value`      LONGTEXT     NOT NULL,
                `expires_at` INT UNSIGNED NOT NULL DEFAULT 0,
                INDEX `idx_expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function key(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * {@inheritdoc}
     * Uses DB transaction for atomicity.
     */
    public function increment(string $key, int $ttl): int
    {
        return $this->connection->transaction(function () use ($key, $ttl) {
            $fullKey = $this->key($key);
            $now     = time();

            $row = $this->connection->query()
                ->table($this->table)
                ->where('key', $fullKey)
                ->first();

            // No row or expired → reset to 1
            if ($row === null || ($row['expires_at'] > 0 && $row['expires_at'] <= $now)) {
                $this->connection->query()
                    ->table($this->table)
                    ->updateOrInsert(
                        ['key' => $fullKey],
                        ['value' => '1', 'expires_at' => $now + $ttl],
                    );

                return 1;
            }

            $newCount = (int) $row['value'] + 1;

            $this->connection->query()
                ->table($this->table)
                ->where('key', $fullKey)
                ->update(['value' => (string) $newCount]);

            return $newCount;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getData(string $key): ?array
    {
        $row = $this->connection->query()
            ->table($this->table)
            ->where('key', $this->key($key))
            ->first();

        if ($row === null) {
            return null;
        }

        if ($row['expires_at'] > 0 && $row['expires_at'] <= time()) {
            $this->reset($key);
            return null;
        }

        return json_decode($row['value'], true);
    }

    /**
     * {@inheritdoc}
     */
    public function setData(string $key, array $data, int $ttl): bool
    {
        return $this->connection->query()
            ->table($this->table)
            ->updateOrInsert(
                ['key' => $this->key($key)],
                ['value' => json_encode($data), 'expires_at' => time() + $ttl],
            );
    }

    /**
     * {@inheritdoc}
     */
    public function reset(string $key): bool
    {
        return (bool) $this->connection->query()
            ->table($this->table)
            ->where('key', $this->key($key))
            ->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function ttl(string $key): int
    {
        $row = $this->connection->query()
            ->table($this->table)
            ->where('key', $this->key($key))
            ->first();

        if ($row === null) {
            return -2;
        }

        if ($row['expires_at'] === 0) {
            return -1;
        }

        $remaining = $row['expires_at'] - time();

        return $remaining > 0 ? $remaining : -2;
    }
}
