<?php

namespace Ryra\Core\Jwt\Blacklist;

use Ryra\Core\Contracts\Database\Connection;
use Ryra\Core\Database\DB;
use Ryra\Core\Jwt\Exception\JWTException;
use Ryra\Core\Jwt\Token;

class DatabaseBlacklist implements BlacklistInterface
{
    private static bool $booted = false;

    public function __construct(
        private Connection $connection,
        private string $table = 'jwt_blacklist',
    ) {
        $this->ensureTableExists();
    }

    public function add(Token $token): void
    {
        $jti = $token->identifiedBy();
        if ($jti === null) {
            throw new JWTException('Token must have a JTI claim to be blacklisted.');
        }

        $exp = $token->expiresAt();
        if ($exp === null) {
            throw new JWTException('Token must have an expiration time to be blacklisted.');
        }

        if ($exp <= \time()) {
            return;
        }

        $this->connection->query()
            ->table($this->table)
            ->insertOrIgnore(['jti' => $jti, 'expires_at' => $exp]);
    }

    public function has(Token $token): bool
    {
        $jti = $token->identifiedBy();
        if ($jti === null) {
            return false;
        }

        return $this->connection
            ->query()
            ->table($this->table)
            ->where('jti', $jti)
            ->where('expires_at', \time(), '>')
            ->exists();
    }

    public function flush(): void
    {
        $this->connection
            ->query()
            ->table($this->table)
            ->where('expires_at', \time(), '<=')
            ->delete();
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
        $driver = $this->connection->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $table  = $this->connection->getPrefix() . $this->table;

        $sql = match ($driver) {
            'mysql' => "
                CREATE TABLE `{$table}` (
                    `jti` VARCHAR(64) NOT NULL PRIMARY KEY,
                    `expires_at` INT NOT NULL
                ) ENGINE=InnoDB
            ",
            'pgsql' => "
                CREATE TABLE \"{$table}\" (
                    \"jti\" VARCHAR(64) NOT NULL PRIMARY KEY,
                    \"expires_at\" INT NOT NULL
                )
            ",
            'sqlite' => "
                CREATE TABLE \"{$table}\" (
                    \"jti\" VARCHAR(64) NOT NULL PRIMARY KEY,
                    \"expires_at\" INT NOT NULL
                )
            ",
            default => throw new \RuntimeException("Unsupported database driver: {$driver}"),
        };

        $this->connection->statement($sql);
    }
}
