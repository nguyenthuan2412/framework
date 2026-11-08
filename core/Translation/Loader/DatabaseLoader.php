<?php

namespace Ryra\Core\Translation\Loader;

use Ryra\Core\Contracts\Translation\Loader;
use Ryra\Core\Database\Connection;
use Ryra\Core\Database\DB;

class DatabaseLoader implements Loader
{
    /**
     * Whether the table has been checked/created.
     */
    protected static bool $tableChecked = false;

    /**
     * Loaded translations cache.
     *
     * @var array<string, array<string, array>>
     */
    protected array $loaded = [];

    /**
     * Create a new database loader instance.
     *
     * @param Connection $connection
     * @param string $table
     */
    public function __construct(
        protected Connection $connection,
        protected string $table = 'translations'
    ) {}

    /**
     * {@inheritdoc}
     */
    public function load(string $locale, string $namespace): array
    {
        // Return cached if available
        if (isset($this->loaded[$locale][$namespace])) {
            return $this->loaded[$locale][$namespace];
        }

        $this->ensureTableExists();

        $rows = $this->connection
            ->query()
            ->table($this->table)
            ->where('locale', $locale)
            ->where('namespace', $namespace)
            ->get();

        $translations = [];
        foreach ($rows as $row) {
            $translations[$row['key']] = $row['value'];
        }

        if (!isset($this->loaded[$locale])) {
            $this->loaded[$locale] = [];
        }

        return $this->loaded[$locale][$namespace] = $translations;
    }

    /**
     * {@inheritdoc}
     */
    public function namespaces(string $locale): array
    {
        $this->ensureTableExists();

        return $this->connection
            ->query()
            ->table($this->table)
            ->where('locale', $locale)
            ->distinct()
            ->pluck('namespace')
            ->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function addPath(string $path, ?string $namespace = null): void
    {
        // Not applicable for database loader
    }

    /**
     * Set or update a translation in the database.
     *
     * @param string $locale
     * @param string $namespace
     * @param string $key
     * @param string $value
     * @return bool
     */
    public function set(string $locale, string $namespace, string $key, string $value): bool
    {
        $this->ensureTableExists();

        $result = $this->connection
            ->query()
            ->table($this->table)
            ->updateOrInsert(
                ['locale' => $locale, 'namespace' => $namespace, 'key' => $key],
                ['value' => $value]
            );

        // Invalidate cache
        unset($this->loaded[$locale][$namespace]);

        return $result;
    }

    /**
     * Set multiple translations at once.
     *
     * @param string $locale
     * @param string $namespace
     * @param array<string, string> $translations
     * @return bool
     */
    public function setMany(string $locale, string $namespace, array $translations): bool
    {
        $this->ensureTableExists();

        return $this->connection->transaction(function () use ($locale, $namespace, $translations) {
            foreach ($translations as $key => $value) {
                $this->connection
                    ->query()
                    ->table($this->table)
                    ->updateOrInsert(
                        ['locale' => $locale, 'namespace' => $namespace, 'key' => $key],
                        ['value' => $value]
                    );
            }

            // Invalidate cache
            unset($this->loaded[$locale][$namespace]);

            return true;
        });
    }

    /**
     * Delete a translation from the database.
     *
     * @param string $locale
     * @param string $namespace
     * @param string $key
     * @return bool
     */
    public function delete(string $locale, string $namespace, string $key): bool
    {
        $this->ensureTableExists();

        $result = (bool) $this->connection
            ->query()
            ->table($this->table)
            ->where('locale', $locale)
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->delete();

        // Invalidate cache
        unset($this->loaded[$locale][$namespace]);

        return $result;
    }

    /**
     * Delete all translations for a namespace.
     *
     * @param string $locale
     * @param string $namespace
     * @return bool
     */
    public function deleteNamespace(string $locale, string $namespace): bool
    {
        $this->ensureTableExists();

        $result = (bool) $this->connection
            ->query()
            ->table($this->table)
            ->where('locale', $locale)
            ->where('namespace', $namespace)
            ->delete();

        // Invalidate cache
        unset($this->loaded[$locale][$namespace]);

        return $result;
    }

    /**
     * Import translations from array.
     *
     * @param string $locale
     * @param string $namespace
     * @param array<string, string> $translations
     * @param bool $overwrite Whether to overwrite existing translations
     * @return int Number of translations imported
     */
    public function import(string $locale, string $namespace, array $translations, bool $overwrite = false): int
    {
        $this->ensureTableExists();

        $count = 0;

        $this->connection->transaction(function () use ($locale, $namespace, $translations, $overwrite, &$count) {
            foreach ($translations as $key => $value) {
                if (!is_string($value)) {
                    continue;
                }

                $exists = $this->connection
                    ->query()
                    ->table($this->table)
                    ->where('locale', $locale)
                    ->where('namespace', $namespace)
                    ->where('key', $key)
                    ->exists();

                if ($exists && !$overwrite) {
                    continue;
                }

                $this->connection
                    ->query()
                    ->table($this->table)
                    ->updateOrInsert(
                        ['locale' => $locale, 'namespace' => $namespace, 'key' => $key],
                        ['value' => $value]
                    );

                $count++;
            }
        });

        // Invalidate cache
        unset($this->loaded[$locale][$namespace]);

        return $count;
    }

    /**
     * Clear the loaded translations cache.
     *
     * @param string|null $locale
     * @param string|null $namespace
     * @return void
     */
    public function clearCache(?string $locale = null, ?string $namespace = null): void
    {
        if ($locale === null) {
            $this->loaded = [];
            return;
        }

        if ($namespace === null) {
            unset($this->loaded[$locale]);
            return;
        }

        unset($this->loaded[$locale][$namespace]);
    }

    /**
     * Ensure the translations table exists.
     *
     * @return void
     */
    protected function ensureTableExists(): void
    {
        if (static::$tableChecked) {
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

        static::$tableChecked = true;
    }

    /**
     * Create the translations table.
     *
     * @return void
     */
    protected function createTable(): void
    {
        $table = $this->connection->getPrefix() . $this->table;

        $this->connection->statement("
            CREATE TABLE `{$table}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `locale` VARCHAR(10) NOT NULL,
                `namespace` VARCHAR(100) NOT NULL,
                `key` VARCHAR(255) NOT NULL,
                `value` TEXT NOT NULL,
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_translation` (`locale`, `namespace`, `key`),
                INDEX `idx_locale_namespace` (`locale`, `namespace`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
}
