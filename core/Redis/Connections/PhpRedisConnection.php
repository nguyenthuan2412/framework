<?php

namespace Ryra\Core\Redis\Connections;

use Redis;
use RedisException;

class PhpRedisConnection extends Connection
{
    protected int $lastQueryTime;

    public function __construct(Redis $client)
    {
        parent::__construct($client);
        $this->lastQueryTime = time();
    }

    public function ping(): bool
    {
        try {
            $result = $this->client->ping();
            return $result === true || $result === '+PONG';
        } catch (RedisException $e) {
            return false;
        }
    }

    public function disconnect(): void
    {
        try {
            $this->client->close();
        } catch (RedisException $e) {
            // Ignore
        }
    }

    // ==================== String Commands ====================

    public function get(string $key): mixed
    {
        $this->lastQueryTime = time();
        $result = $this->client->get($key);
        return $result !== false ? $result : null;
    }

    public function set(string $key, mixed $value, mixed $options = null): bool
    {
        $this->lastQueryTime = time();
        if ($options !== null) {
            return (bool) $this->client->set($key, $value, $options);
        }
        return (bool) $this->client->set($key, $value);
    }

    public function setex(string $key, int $seconds, mixed $value): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->setex($key, $seconds, $value);
    }

    public function setnx(string $key, mixed $value): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->setnx($key, $value);
    }

    public function del(string ...$keys): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->del(...$keys);
    }

    public function exists(string $key): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->exists($key);
    }

    public function expire(string $key, int $seconds): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->expire($key, $seconds);
    }

    public function ttl(string $key): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->ttl($key);
    }

    public function pexpire(string $key, int $milliseconds): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->pExpire($key, $milliseconds);
    }

    public function pttl(string $key): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->pttl($key);
    }

    public function persist(string $key): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->persist($key);
    }

    public function mget(array $keys): array
    {
        $this->lastQueryTime = time();
        $result = $this->client->mget($keys);
        return array_map(fn($v) => $v !== false ? $v : null, $result);
    }

    public function mset(array $dictionary): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->mset($dictionary);
    }

    public function incr(string $key): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->incr($key);
    }

    public function incrby(string $key, int $value): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->incrBy($key, $value);
    }

    public function incrbyfloat(string $key, float $value): float
    {
        $this->lastQueryTime = time();
        return (float) $this->client->incrByFloat($key, $value);
    }

    public function decr(string $key): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->decr($key);
    }

    public function decrby(string $key, int $value): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->decrBy($key, $value);
    }

    public function append(string $key, string $value): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->append($key, $value);
    }

    public function strlen(string $key): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->strlen($key);
    }

    // ==================== Key Commands ====================

    public function keys(string $pattern): array
    {
        $this->lastQueryTime = time();
        return $this->client->keys($pattern) ?: [];
    }

    public function scan(?int &$cursor, string $pattern = '*', int $count = 10): array|false
    {
        $this->lastQueryTime = time();
        return $this->client->scan($cursor, $pattern, $count);
    }

    public function type(string $key): string
    {
        $this->lastQueryTime = time();
        $type = $this->client->type($key);
        return match ($type) {
            Redis::REDIS_STRING => 'string',
            Redis::REDIS_SET => 'set',
            Redis::REDIS_LIST => 'list',
            Redis::REDIS_ZSET => 'zset',
            Redis::REDIS_HASH => 'hash',
            Redis::REDIS_STREAM => 'stream',
            default => 'none',
        };
    }

    public function rename(string $key, string $newKey): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->rename($key, $newKey);
    }

    public function renamenx(string $key, string $newKey): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->renameNx($key, $newKey);
    }

    public function select(int $database): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->select($database);
    }

    public function flushdb(): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->flushDB();
    }

    // ==================== Hash Commands ====================

    public function hset(string $key, string $field, mixed $value): int|bool
    {
        $this->lastQueryTime = time();
        return $this->client->hSet($key, $field, $value);
    }

    public function hget(string $key, string $field): mixed
    {
        $this->lastQueryTime = time();
        $result = $this->client->hGet($key, $field);
        return $result !== false ? $result : null;
    }

    public function hgetall(string $key): array
    {
        $this->lastQueryTime = time();
        return $this->client->hGetAll($key) ?: [];
    }

    public function hexists(string $key, string $field): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->hExists($key, $field);
    }

    public function hdel(string $key, string ...$fields): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->hDel($key, ...$fields);
    }

    public function hmset(string $key, array $dictionary): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->hMSet($key, $dictionary);
    }

    public function hmget(string $key, array $fields): array
    {
        $this->lastQueryTime = time();
        $result = $this->client->hMGet($key, $fields);
        return array_map(fn($v) => $v !== false ? $v : null, $result ?: []);
    }

    public function hincrby(string $key, string $field, int $value): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->hIncrBy($key, $field, $value);
    }

    public function hincrbyfloat(string $key, string $field, float $value): float
    {
        $this->lastQueryTime = time();
        return (float) $this->client->hIncrByFloat($key, $field, $value);
    }

    public function hlen(string $key): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->hLen($key);
    }

    public function hkeys(string $key): array
    {
        $this->lastQueryTime = time();
        return $this->client->hKeys($key) ?: [];
    }

    public function hvals(string $key): array
    {
        $this->lastQueryTime = time();
        return $this->client->hVals($key) ?: [];
    }

    public function hsetnx(string $key, string $field, mixed $value): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->hSetNx($key, $field, $value);
    }

    // ==================== List Commands ====================

    public function lpush(string $key, mixed ...$values): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->lPush($key, ...$values);
    }

    public function rpush(string $key, mixed ...$values): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->rPush($key, ...$values);
    }

    public function lpop(string $key): mixed
    {
        $this->lastQueryTime = time();
        $result = $this->client->lPop($key);
        return $result !== false ? $result : null;
    }

    public function rpop(string $key): mixed
    {
        $this->lastQueryTime = time();
        $result = $this->client->rPop($key);
        return $result !== false ? $result : null;
    }

    public function blpop(array $keys, int|float $timeout): ?array
    {
        $this->lastQueryTime = time();
        $result = $this->client->blPop($keys, $timeout);
        return $result ?: null;
    }

    public function brpop(array $keys, int|float $timeout): ?array
    {
        $this->lastQueryTime = time();
        $result = $this->client->brPop($keys, $timeout);
        return $result ?: null;
    }

    public function llen(string $key): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->lLen($key);
    }

    public function lrange(string $key, int $start, int $stop): array
    {
        $this->lastQueryTime = time();
        return $this->client->lRange($key, $start, $stop) ?: [];
    }

    public function lindex(string $key, int $index): mixed
    {
        $this->lastQueryTime = time();
        $result = $this->client->lIndex($key, $index);
        return $result !== false ? $result : null;
    }

    public function lset(string $key, int $index, mixed $value): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->lSet($key, $index, $value);
    }

    public function lrem(string $key, int $count, mixed $value): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->lRem($key, $value, $count);
    }

    // ==================== Set Commands ====================

    public function sadd(string $key, mixed ...$members): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->sAdd($key, ...$members);
    }

    public function srem(string $key, mixed ...$members): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->sRem($key, ...$members);
    }

    public function smembers(string $key): array
    {
        $this->lastQueryTime = time();
        return $this->client->sMembers($key) ?: [];
    }

    public function sismember(string $key, mixed $member): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->sIsMember($key, $member);
    }

    public function scard(string $key): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->sCard($key);
    }

    public function sunion(string ...$keys): array
    {
        $this->lastQueryTime = time();
        return $this->client->sUnion(...$keys) ?: [];
    }

    public function sinter(string ...$keys): array
    {
        $this->lastQueryTime = time();
        return $this->client->sInter(...$keys) ?: [];
    }

    public function sdiff(string ...$keys): array
    {
        $this->lastQueryTime = time();
        return $this->client->sDiff(...$keys) ?: [];
    }

    public function spop(string $key, int $count = 1): mixed
    {
        $this->lastQueryTime = time();
        $result = $this->client->sPop($key, $count);
        return $result !== false ? $result : null;
    }

    public function srandmember(string $key, ?int $count = null): mixed
    {
        $this->lastQueryTime = time();
        if ($count !== null) {
            return $this->client->sRandMember($key, $count) ?: [];
        }
        $result = $this->client->sRandMember($key);
        return $result !== false ? $result : null;
    }

    // ==================== Sorted Set Commands ====================

    public function zadd(string $key, float $score, mixed $member, mixed ...$extra): int
    {
        $this->lastQueryTime = time();
        if (empty($extra)) {
            return (int) $this->client->zAdd($key, $score, $member);
        }
        // Support multiple score/member pairs: zadd(key, score1, member1, score2, member2, ...)
        $args = [$score, $member, ...$extra];
        return (int) $this->client->zAdd($key, ...$args);
    }

    public function zrem(string $key, mixed ...$members): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->zRem($key, ...$members);
    }

    public function zscore(string $key, mixed $member): ?float
    {
        $this->lastQueryTime = time();
        $result = $this->client->zScore($key, $member);
        return $result !== false ? (float) $result : null;
    }

    public function zrank(string $key, mixed $member): ?int
    {
        $this->lastQueryTime = time();
        $result = $this->client->zRank($key, $member);
        return $result !== false ? (int) $result : null;
    }

    public function zrevrank(string $key, mixed $member): ?int
    {
        $this->lastQueryTime = time();
        $result = $this->client->zRevRank($key, $member);
        return $result !== false ? (int) $result : null;
    }

    public function zrange(string $key, int $start, int $stop, bool $withScores = false): array
    {
        $this->lastQueryTime = time();
        if ($withScores) {
            return $this->client->zRange($key, $start, $stop, true) ?: [];
        }
        return $this->client->zRange($key, $start, $stop) ?: [];
    }

    public function zrevrange(string $key, int $start, int $stop, bool $withScores = false): array
    {
        $this->lastQueryTime = time();
        if ($withScores) {
            return $this->client->zRevRange($key, $start, $stop, true) ?: [];
        }
        return $this->client->zRevRange($key, $start, $stop) ?: [];
    }

    public function zrangebyscore(string $key, string $min, string $max, array $options = []): array
    {
        $this->lastQueryTime = time();
        return $this->client->zRangeByScore($key, $min, $max, $options) ?: [];
    }

    public function zrevrangebyscore(string $key, string $max, string $min, array $options = []): array
    {
        $this->lastQueryTime = time();
        return $this->client->zRevRangeByScore($key, $max, $min, $options) ?: [];
    }

    public function zcard(string $key): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->zCard($key);
    }

    public function zcount(string $key, string $min, string $max): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->zCount($key, $min, $max);
    }

    public function zincrby(string $key, float $increment, mixed $member): float
    {
        $this->lastQueryTime = time();
        return (float) $this->client->zIncrBy($key, $increment, $member);
    }

    public function zremrangebyrank(string $key, int $start, int $stop): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->zRemRangeByRank($key, $start, $stop);
    }

    public function zremrangebyscore(string $key, string $min, string $max): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->zRemRangeByScore($key, $min, $max);
    }

    // ==================== Transaction Commands ====================

    public function multi(): static
    {
        $this->lastQueryTime = time();
        $this->client->multi();
        return $this;
    }

    public function exec(): array|false
    {
        $this->lastQueryTime = time();
        return $this->client->exec();
    }

    public function discard(): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->discard();
    }

    public function transaction(\Closure $callback): array|false
    {
        $this->multi();
        try {
            $callback($this);
            return $this->exec();
        } catch (\Throwable $e) {
            $this->discard();
            throw $e;
        }
    }

    // ==================== Lua Scripting ====================

    public function eval(string $script, array $keys = [], array $args = []): mixed
    {
        $this->lastQueryTime = time();
        return $this->client->eval($script, array_merge($keys, $args), count($keys));
    }

    public function evalsha(string $sha, array $keys = [], array $args = []): mixed
    {
        $this->lastQueryTime = time();
        return $this->client->evalSha($sha, array_merge($keys, $args), count($keys));
    }

    // ==================== Pub/Sub Commands ====================

    public function publish(string $channel, string $message): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->publish($channel, $message);
    }

    public function subscribe(array $channels, \Closure $callback): void
    {
        $this->lastQueryTime = time();
        $this->client->subscribe($channels, function ($redis, $channel, $message) use ($callback) {
            $callback($channel, $message);
        });
    }

    public function psubscribe(array $patterns, \Closure $callback): void
    {
        $this->lastQueryTime = time();
        $this->client->psubscribe($patterns, function ($redis, $pattern, $channel, $message) use ($callback) {
            $callback($channel, $message, $pattern);
        });
    }

    // ==================== Misc ====================

    public function getLastQueryTime(): int
    {
        return $this->lastQueryTime;
    }
}
