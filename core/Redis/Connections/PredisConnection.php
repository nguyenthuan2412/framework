<?php

namespace Ryra\Core\Redis\Connections;

use Predis\Client;

class PredisConnection extends Connection
{
    protected int $lastQueryTime;

    public function __construct(Client $client)
    {
        parent::__construct($client);
        $this->lastQueryTime = time();
    }

    public function ping(): bool
    {
        try {
            $response = $this->client->ping();
            return $response->getPayload() === 'PONG';
        } catch (\Exception $e) {
            return false;
        }
    }

    public function disconnect(): void
    {
        try {
            $this->client->disconnect();
        } catch (\Exception $e) {
            // Ignore
        }
    }

    // ==================== String Commands ====================

    public function get(string $key): mixed
    {
        $this->lastQueryTime = time();
        return $this->client->get($key);
    }

    public function set(string $key, mixed $value, mixed $options = null): bool
    {
        $this->lastQueryTime = time();
        if ($options !== null) {
            $response = $this->client->set($key, $value, ...(array) $options);
        } else {
            $response = $this->client->set($key, $value);
        }
        return $this->toBool($response);
    }

    public function setex(string $key, int $seconds, mixed $value): bool
    {
        $this->lastQueryTime = time();
        return $this->toBool($this->client->setex($key, $seconds, $value));
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
        return (bool) $this->client->pexpire($key, $milliseconds);
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
        return $this->client->mget($keys) ?: [];
    }

    public function mset(array $dictionary): bool
    {
        $this->lastQueryTime = time();
        return $this->toBool($this->client->mset($dictionary));
    }

    public function incr(string $key): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->incr($key);
    }

    public function incrby(string $key, int $value): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->incrby($key, $value);
    }

    public function incrbyfloat(string $key, float $value): float
    {
        $this->lastQueryTime = time();
        return (float) $this->client->incrbyfloat($key, $value);
    }

    public function decr(string $key): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->decr($key);
    }

    public function decrby(string $key, int $value): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->decrby($key, $value);
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
        $result = $this->client->scan($cursor, ['MATCH' => $pattern, 'COUNT' => $count]);

        if ($result === null) {
            return false;
        }

        $cursor = (int) $result[0];
        return $result[1] ?? [];
    }

    public function type(string $key): string
    {
        $this->lastQueryTime = time();
        $response = $this->client->type($key);
        return is_object($response) ? $response->getPayload() : (string) $response;
    }

    public function rename(string $key, string $newKey): bool
    {
        $this->lastQueryTime = time();
        return $this->toBool($this->client->rename($key, $newKey));
    }

    public function renamenx(string $key, string $newKey): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->renamenx($key, $newKey);
    }

    public function select(int $database): bool
    {
        $this->lastQueryTime = time();
        return $this->toBool($this->client->select($database));
    }

    public function flushdb(): bool
    {
        $this->lastQueryTime = time();
        return $this->toBool($this->client->flushdb());
    }

    // ==================== Hash Commands ====================

    public function hset(string $key, string $field, mixed $value): int|bool
    {
        $this->lastQueryTime = time();
        return (int) $this->client->hset($key, $field, $value);
    }

    public function hget(string $key, string $field): mixed
    {
        $this->lastQueryTime = time();
        return $this->client->hget($key, $field);
    }

    public function hgetall(string $key): array
    {
        $this->lastQueryTime = time();
        return $this->client->hgetall($key) ?: [];
    }

    public function hexists(string $key, string $field): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->hexists($key, $field);
    }

    public function hdel(string $key, string ...$fields): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->hdel($key, ...$fields);
    }

    public function hmset(string $key, array $dictionary): bool
    {
        $this->lastQueryTime = time();
        return $this->toBool($this->client->hmset($key, $dictionary));
    }

    public function hmget(string $key, array $fields): array
    {
        $this->lastQueryTime = time();
        $values = $this->client->hmget($key, $fields) ?: [];
        return array_combine($fields, $values);
    }

    public function hincrby(string $key, string $field, int $value): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->hincrby($key, $field, $value);
    }

    public function hincrbyfloat(string $key, string $field, float $value): float
    {
        $this->lastQueryTime = time();
        return (float) $this->client->hincrbyfloat($key, $field, $value);
    }

    public function hlen(string $key): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->hlen($key);
    }

    public function hkeys(string $key): array
    {
        $this->lastQueryTime = time();
        return $this->client->hkeys($key) ?: [];
    }

    public function hvals(string $key): array
    {
        $this->lastQueryTime = time();
        return $this->client->hvals($key) ?: [];
    }

    public function hsetnx(string $key, string $field, mixed $value): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->hsetnx($key, $field, $value);
    }

    // ==================== List Commands ====================

    public function lpush(string $key, mixed ...$values): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->lpush($key, ...$values);
    }

    public function rpush(string $key, mixed ...$values): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->rpush($key, ...$values);
    }

    public function lpop(string $key): mixed
    {
        $this->lastQueryTime = time();
        return $this->client->lpop($key);
    }

    public function rpop(string $key): mixed
    {
        $this->lastQueryTime = time();
        return $this->client->rpop($key);
    }

    public function blpop(array $keys, int|float $timeout): ?array
    {
        $this->lastQueryTime = time();
        $result = $this->client->blpop($keys, (int) $timeout);
        return $result ?: null;
    }

    public function brpop(array $keys, int|float $timeout): ?array
    {
        $this->lastQueryTime = time();
        $result = $this->client->brpop($keys, (int) $timeout);
        return $result ?: null;
    }

    public function llen(string $key): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->llen($key);
    }

    public function lrange(string $key, int $start, int $stop): array
    {
        $this->lastQueryTime = time();
        return $this->client->lrange($key, $start, $stop) ?: [];
    }

    public function lindex(string $key, int $index): mixed
    {
        $this->lastQueryTime = time();
        return $this->client->lindex($key, $index);
    }

    public function lset(string $key, int $index, mixed $value): bool
    {
        $this->lastQueryTime = time();
        return $this->toBool($this->client->lset($key, $index, $value));
    }

    public function lrem(string $key, int $count, mixed $value): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->lrem($key, $count, $value);
    }

    // ==================== Set Commands ====================

    public function sadd(string $key, mixed ...$members): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->sadd($key, ...$members);
    }

    public function srem(string $key, mixed ...$members): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->srem($key, ...$members);
    }

    public function smembers(string $key): array
    {
        $this->lastQueryTime = time();
        return $this->client->smembers($key) ?: [];
    }

    public function sismember(string $key, mixed $member): bool
    {
        $this->lastQueryTime = time();
        return (bool) $this->client->sismember($key, $member);
    }

    public function scard(string $key): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->scard($key);
    }

    public function sunion(string ...$keys): array
    {
        $this->lastQueryTime = time();
        return $this->client->sunion(...$keys) ?: [];
    }

    public function sinter(string ...$keys): array
    {
        $this->lastQueryTime = time();
        return $this->client->sinter(...$keys) ?: [];
    }

    public function sdiff(string ...$keys): array
    {
        $this->lastQueryTime = time();
        return $this->client->sdiff(...$keys) ?: [];
    }

    public function spop(string $key, int $count = 1): mixed
    {
        $this->lastQueryTime = time();
        if ($count > 1) {
            return $this->client->spop($key, $count) ?: [];
        }
        return $this->client->spop($key);
    }

    public function srandmember(string $key, ?int $count = null): mixed
    {
        $this->lastQueryTime = time();
        if ($count !== null) {
            return $this->client->srandmember($key, $count) ?: [];
        }
        return $this->client->srandmember($key);
    }

    // ==================== Sorted Set Commands ====================

    public function zadd(string $key, float $score, mixed $member, mixed ...$extra): int
    {
        $this->lastQueryTime = time();
        if (empty($extra)) {
            return (int) $this->client->zadd($key, $score, $member);
        }
        // Support multiple score/member pairs
        $members = [$member => $score];
        for ($i = 0; $i < count($extra); $i += 2) {
            if (isset($extra[$i + 1])) {
                $members[$extra[$i + 1]] = $extra[$i];
            }
        }
        return (int) $this->client->zadd($key, ...array_reduce(
            array_keys($members),
            fn($carry, $m) => array_merge($carry, [$members[$m], $m]),
            []
        ));
    }

    public function zrem(string $key, mixed ...$members): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->zrem($key, ...$members);
    }

    public function zscore(string $key, mixed $member): ?float
    {
        $this->lastQueryTime = time();
        $result = $this->client->zscore($key, $member);
        return $result !== null ? (float) $result : null;
    }

    public function zrank(string $key, mixed $member): ?int
    {
        $this->lastQueryTime = time();
        $result = $this->client->zrank($key, $member);
        return $result !== null ? (int) $result : null;
    }

    public function zrevrank(string $key, mixed $member): ?int
    {
        $this->lastQueryTime = time();
        $result = $this->client->zrevrank($key, $member);
        return $result !== null ? (int) $result : null;
    }

    public function zrange(string $key, int $start, int $stop, bool $withScores = false): array
    {
        $this->lastQueryTime = time();
        if ($withScores) {
            return $this->client->zrange($key, $start, $stop, ['WITHSCORES' => true]) ?: [];
        }
        return $this->client->zrange($key, $start, $stop) ?: [];
    }

    public function zrevrange(string $key, int $start, int $stop, bool $withScores = false): array
    {
        $this->lastQueryTime = time();
        if ($withScores) {
            return $this->client->zrevrange($key, $start, $stop, ['WITHSCORES' => true]) ?: [];
        }
        return $this->client->zrevrange($key, $start, $stop) ?: [];
    }

    public function zrangebyscore(string $key, string $min, string $max, array $options = []): array
    {
        $this->lastQueryTime = time();
        return $this->client->zrangebyscore($key, $min, $max, $options) ?: [];
    }

    public function zrevrangebyscore(string $key, string $max, string $min, array $options = []): array
    {
        $this->lastQueryTime = time();
        return $this->client->zrevrangebyscore($key, $max, $min, $options) ?: [];
    }

    public function zcard(string $key): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->zcard($key);
    }

    public function zcount(string $key, string $min, string $max): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->zcount($key, $min, $max);
    }

    public function zincrby(string $key, float $increment, mixed $member): float
    {
        $this->lastQueryTime = time();
        return (float) $this->client->zincrby($key, $increment, $member);
    }

    public function zremrangebyrank(string $key, int $start, int $stop): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->zremrangebyrank($key, $start, $stop);
    }

    public function zremrangebyscore(string $key, string $min, string $max): int
    {
        $this->lastQueryTime = time();
        return (int) $this->client->zremrangebyscore($key, $min, $max);
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
        $result = $this->client->exec();
        return is_array($result) ? $result : false;
    }

    public function discard(): bool
    {
        $this->lastQueryTime = time();
        return $this->toBool($this->client->discard());
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
        return $this->client->eval($script, count($keys), ...$keys, ...$args);
    }

    public function evalsha(string $sha, array $keys = [], array $args = []): mixed
    {
        $this->lastQueryTime = time();
        return $this->client->evalsha($sha, count($keys), ...$keys, ...$args);
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
        $loop = $this->client->pubSubLoop();
        $loop->subscribe(...$channels);

        foreach ($loop as $message) {
            if ($message->kind === 'message') {
                $callback($message->channel, $message->payload);
            }
        }
    }

    public function psubscribe(array $patterns, \Closure $callback): void
    {
        $this->lastQueryTime = time();
        $loop = $this->client->pubSubLoop();
        $loop->psubscribe(...$patterns);

        foreach ($loop as $message) {
            if ($message->kind === 'pmessage') {
                $callback($message->channel, $message->payload, $message->pattern);
            }
        }
    }

    // ==================== Misc ====================

    public function getLastQueryTime(): int
    {
        return $this->lastQueryTime;
    }

    /**
     * Convert Predis response to bool.
     */
    protected function toBool(mixed $response): bool
    {
        if (is_object($response) && method_exists($response, 'getPayload')) {
            return $response->getPayload() === 'OK';
        }
        return (bool) $response;
    }
}
