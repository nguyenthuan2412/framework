<?php

namespace Ryra\Core\Redis\Connections;

use Closure;

abstract class Connection
{
    protected object $client;

    protected ?string $name = null;

    public function __construct(object $client)
    {
        $this->client = $client;
    }

    /**
     * Lấy raw client.
     */
    public function client(): object
    {
        return $this->client;
    }

    /**
     * Set connection name.
     */
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get connection name.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Thực thi Redis command.
     */
    public function command(string $method, array $parameters = []): mixed
    {
        return $this->client->{$method}(...$parameters);
    }

    /**
     * Ping Redis server.
     */
    abstract public function ping(): bool;

    /**
     * Đóng kết nối.
     */
    abstract public function disconnect(): void;

    // ==================== String Commands ====================

    /**
     * Get a value from Redis.
     */
    abstract public function get(string $key): mixed;

    /**
     * Set a value in Redis.
     */
    abstract public function set(string $key, mixed $value, mixed $options = null): bool;

    /**
     * Set a value with expiration.
     */
    abstract public function setex(string $key, int $seconds, mixed $value): bool;

    /**
     * Set value only if key does not exist.
     */
    abstract public function setnx(string $key, mixed $value): bool;

    /**
     * Delete keys.
     */
    abstract public function del(string ...$keys): int;

    /**
     * Check if key exists.
     */
    abstract public function exists(string $key): bool;

    /**
     * Set expiration on key.
     */
    abstract public function expire(string $key, int $seconds): bool;

    /**
     * Get TTL of key.
     */
    abstract public function ttl(string $key): int;

    /**
     * Set expiration in milliseconds.
     */
    abstract public function pexpire(string $key, int $milliseconds): bool;

    /**
     * Get TTL in milliseconds.
     */
    abstract public function pttl(string $key): int;

    /**
     * Remove expiration from key.
     */
    abstract public function persist(string $key): bool;

    /**
     * Get multiple values.
     */
    abstract public function mget(array $keys): array;

    /**
     * Set multiple values.
     */
    abstract public function mset(array $dictionary): bool;

    /**
     * Increment value.
     */
    abstract public function incr(string $key): int;

    /**
     * Increment by value.
     */
    abstract public function incrby(string $key, int $value): int;

    /**
     * Increment by float.
     */
    abstract public function incrbyfloat(string $key, float $value): float;

    /**
     * Decrement value.
     */
    abstract public function decr(string $key): int;

    /**
     * Decrement by value.
     */
    abstract public function decrby(string $key, int $value): int;

    /**
     * Append value to key.
     */
    abstract public function append(string $key, string $value): int;

    /**
     * Get string length of value.
     */
    abstract public function strlen(string $key): int;

    // ==================== Key Commands ====================

    /**
     * Get keys by pattern.
     */
    abstract public function keys(string $pattern): array;

    /**
     * Scan keys.
     */
    abstract public function scan(?int &$cursor, string $pattern = '*', int $count = 10): array|false;

    /**
     * Get key type.
     */
    abstract public function type(string $key): string;

    /**
     * Rename key.
     */
    abstract public function rename(string $key, string $newKey): bool;

    /**
     * Rename key only if new key does not exist.
     */
    abstract public function renamenx(string $key, string $newKey): bool;

    /**
     * Select database.
     */
    abstract public function select(int $database): bool;

    /**
     * Flush database.
     */
    abstract public function flushdb(): bool;

    // ==================== Hash Commands ====================

    /**
     * Hash set.
     */
    abstract public function hset(string $key, string $field, mixed $value): int|bool;

    /**
     * Hash get.
     */
    abstract public function hget(string $key, string $field): mixed;

    /**
     * Hash get all.
     */
    abstract public function hgetall(string $key): array;

    /**
     * Hash exists.
     */
    abstract public function hexists(string $key, string $field): bool;

    /**
     * Hash delete.
     */
    abstract public function hdel(string $key, string ...$fields): int;

    /**
     * Hash multiple set.
     */
    abstract public function hmset(string $key, array $dictionary): bool;

    /**
     * Hash multiple get.
     */
    abstract public function hmget(string $key, array $fields): array;

    /**
     * Hash increment by integer.
     */
    abstract public function hincrby(string $key, string $field, int $value): int;

    /**
     * Hash increment by float.
     */
    abstract public function hincrbyfloat(string $key, string $field, float $value): float;

    /**
     * Hash length (number of fields).
     */
    abstract public function hlen(string $key): int;

    /**
     * Hash keys.
     */
    abstract public function hkeys(string $key): array;

    /**
     * Hash values.
     */
    abstract public function hvals(string $key): array;

    /**
     * Hash set if not exists.
     */
    abstract public function hsetnx(string $key, string $field, mixed $value): bool;

    // ==================== List Commands ====================

    /**
     * List push left.
     */
    abstract public function lpush(string $key, mixed ...$values): int;

    /**
     * List push right.
     */
    abstract public function rpush(string $key, mixed ...$values): int;

    /**
     * List pop left.
     */
    abstract public function lpop(string $key): mixed;

    /**
     * List pop right.
     */
    abstract public function rpop(string $key): mixed;

    /**
     * Blocking list pop left.
     */
    abstract public function blpop(array $keys, int|float $timeout): ?array;

    /**
     * Blocking list pop right.
     */
    abstract public function brpop(array $keys, int|float $timeout): ?array;

    /**
     * List length.
     */
    abstract public function llen(string $key): int;

    /**
     * List range.
     */
    abstract public function lrange(string $key, int $start, int $stop): array;

    /**
     * Get element by index.
     */
    abstract public function lindex(string $key, int $index): mixed;

    /**
     * Set element by index.
     */
    abstract public function lset(string $key, int $index, mixed $value): bool;

    /**
     * Remove elements from list.
     */
    abstract public function lrem(string $key, int $count, mixed $value): int;

    // ==================== Set Commands ====================

    /**
     * Set add.
     */
    abstract public function sadd(string $key, mixed ...$members): int;

    /**
     * Set remove.
     */
    abstract public function srem(string $key, mixed ...$members): int;

    /**
     * Set members.
     */
    abstract public function smembers(string $key): array;

    /**
     * Set is member.
     */
    abstract public function sismember(string $key, mixed $member): bool;

    /**
     * Set cardinality.
     */
    abstract public function scard(string $key): int;

    /**
     * Set union.
     */
    abstract public function sunion(string ...$keys): array;

    /**
     * Set intersection.
     */
    abstract public function sinter(string ...$keys): array;

    /**
     * Set difference.
     */
    abstract public function sdiff(string ...$keys): array;

    /**
     * Remove and return random member.
     */
    abstract public function spop(string $key, int $count = 1): mixed;

    /**
     * Get random member without removing.
     */
    abstract public function srandmember(string $key, ?int $count = null): mixed;

    // ==================== Sorted Set Commands ====================

    /**
     * Add member to sorted set.
     */
    abstract public function zadd(string $key, float $score, mixed $member, mixed ...$extra): int;

    /**
     * Remove member from sorted set.
     */
    abstract public function zrem(string $key, mixed ...$members): int;

    /**
     * Get score of member.
     */
    abstract public function zscore(string $key, mixed $member): ?float;

    /**
     * Get rank of member (ascending).
     */
    abstract public function zrank(string $key, mixed $member): ?int;

    /**
     * Get rank of member (descending).
     */
    abstract public function zrevrank(string $key, mixed $member): ?int;

    /**
     * Get range by rank (ascending).
     */
    abstract public function zrange(string $key, int $start, int $stop, bool $withScores = false): array;

    /**
     * Get range by rank (descending).
     */
    abstract public function zrevrange(string $key, int $start, int $stop, bool $withScores = false): array;

    /**
     * Get range by score.
     */
    abstract public function zrangebyscore(string $key, string $min, string $max, array $options = []): array;

    /**
     * Get reverse range by score.
     */
    abstract public function zrevrangebyscore(string $key, string $max, string $min, array $options = []): array;

    /**
     * Get sorted set cardinality.
     */
    abstract public function zcard(string $key): int;

    /**
     * Count members in score range.
     */
    abstract public function zcount(string $key, string $min, string $max): int;

    /**
     * Increment score of member.
     */
    abstract public function zincrby(string $key, float $increment, mixed $member): float;

    /**
     * Remove members by rank range.
     */
    abstract public function zremrangebyrank(string $key, int $start, int $stop): int;

    /**
     * Remove members by score range.
     */
    abstract public function zremrangebyscore(string $key, string $min, string $max): int;

    // ==================== Transaction Commands ====================

    /**
     * Start transaction.
     */
    abstract public function multi(): static;

    /**
     * Execute transaction.
     */
    abstract public function exec(): array|false;

    /**
     * Discard transaction.
     */
    abstract public function discard(): bool;

    /**
     * Execute callback within a transaction.
     */
    abstract public function transaction(Closure $callback): array|false;

    // ==================== Lua Scripting ====================

    /**
     * Execute Lua script.
     */
    abstract public function eval(string $script, array $keys = [], array $args = []): mixed;

    /**
     * Execute cached Lua script by SHA.
     */
    abstract public function evalsha(string $sha, array $keys = [], array $args = []): mixed;

    // ==================== Pub/Sub Commands ====================

    /**
     * Publish message to channel.
     *
     * @return int Số lượng subscribers nhận được message
     */
    abstract public function publish(string $channel, string $message): int;

    /**
     * Subscribe to channels.
     *
     * Callback nhận: function(string $channel, string $message)
     * Lưu ý: Method này sẽ block cho đến khi unsubscribe.
     */
    abstract public function subscribe(array $channels, Closure $callback): void;

    /**
     * Subscribe to channels by pattern.
     *
     * Callback nhận: function(string $channel, string $message, string $pattern)
     * Lưu ý: Method này sẽ block cho đến khi unsubscribe.
     */
    abstract public function psubscribe(array $patterns, Closure $callback): void;

    /**
     * Forward calls to client.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->command($method, $parameters);
    }
}
