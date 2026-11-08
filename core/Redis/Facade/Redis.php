<?php

namespace Ryra\Core\Redis\Facade;

use Ryra\Core\Redis\Connections\Connection;
use Ryra\Core\Support\Facade;

/**
 * @method static Connection connection(?string $name = null)
 * @method static string getDefaultConnection()
 * @method static array getConnectionConfig(string $name)
 * @method static Connection subscriber(?string $name = null)
 * @method static void release(?string $name = null)
 * @method static void disconnect()
 *
 * String Commands:
 * @method static mixed get(string $key)
 * @method static bool set(string $key, mixed $value, mixed $options = null)
 * @method static bool setex(string $key, int $seconds, mixed $value)
 * @method static bool setnx(string $key, mixed $value)
 * @method static int del(string ...$keys)
 * @method static bool exists(string $key)
 * @method static bool expire(string $key, int $seconds)
 * @method static int ttl(string $key)
 * @method static bool pexpire(string $key, int $milliseconds)
 * @method static int pttl(string $key)
 * @method static bool persist(string $key)
 * @method static array mget(array $keys)
 * @method static bool mset(array $dictionary)
 * @method static int incr(string $key)
 * @method static int incrby(string $key, int $value)
 * @method static float incrbyfloat(string $key, float $value)
 * @method static int decr(string $key)
 * @method static int decrby(string $key, int $value)
 * @method static int append(string $key, string $value)
 * @method static int strlen(string $key)
 *
 * Key Commands:
 * @method static array keys(string $pattern)
 * @method static array|false scan(?int &$cursor, string $pattern = '*', int $count = 10)
 * @method static string type(string $key)
 * @method static bool rename(string $key, string $newKey)
 * @method static bool renamenx(string $key, string $newKey)
 *
 * Hash Commands:
 * @method static int|bool hset(string $key, string $field, mixed $value)
 * @method static mixed hget(string $key, string $field)
 * @method static array hgetall(string $key)
 * @method static bool hexists(string $key, string $field)
 * @method static int hdel(string $key, string ...$fields)
 * @method static bool hmset(string $key, array $dictionary)
 * @method static array hmget(string $key, array $fields)
 * @method static int hincrby(string $key, string $field, int $value)
 * @method static float hincrbyfloat(string $key, string $field, float $value)
 * @method static int hlen(string $key)
 * @method static array hkeys(string $key)
 * @method static array hvals(string $key)
 * @method static bool hsetnx(string $key, string $field, mixed $value)
 *
 * List Commands:
 * @method static int lpush(string $key, mixed ...$values)
 * @method static int rpush(string $key, mixed ...$values)
 * @method static mixed lpop(string $key)
 * @method static mixed rpop(string $key)
 * @method static ?array blpop(array $keys, int|float $timeout)
 * @method static ?array brpop(array $keys, int|float $timeout)
 * @method static int llen(string $key)
 * @method static array lrange(string $key, int $start, int $stop)
 * @method static mixed lindex(string $key, int $index)
 * @method static bool lset(string $key, int $index, mixed $value)
 * @method static int lrem(string $key, int $count, mixed $value)
 *
 * Set Commands:
 * @method static int sadd(string $key, mixed ...$members)
 * @method static int srem(string $key, mixed ...$members)
 * @method static array smembers(string $key)
 * @method static bool sismember(string $key, mixed $member)
 * @method static int scard(string $key)
 * @method static array sunion(string ...$keys)
 * @method static array sinter(string ...$keys)
 * @method static array sdiff(string ...$keys)
 * @method static mixed spop(string $key, int $count = 1)
 * @method static mixed srandmember(string $key, ?int $count = null)
 *
 * Sorted Set Commands:
 * @method static int zadd(string $key, float $score, mixed $member, mixed ...$extra)
 * @method static int zrem(string $key, mixed ...$members)
 * @method static ?float zscore(string $key, mixed $member)
 * @method static ?int zrank(string $key, mixed $member)
 * @method static ?int zrevrank(string $key, mixed $member)
 * @method static array zrange(string $key, int $start, int $stop, bool $withScores = false)
 * @method static array zrevrange(string $key, int $start, int $stop, bool $withScores = false)
 * @method static array zrangebyscore(string $key, string $min, string $max, array $options = [])
 * @method static array zrevrangebyscore(string $key, string $max, string $min, array $options = [])
 * @method static int zcard(string $key)
 * @method static int zcount(string $key, string $min, string $max)
 * @method static float zincrby(string $key, float $increment, mixed $member)
 * @method static int zremrangebyrank(string $key, int $start, int $stop)
 * @method static int zremrangebyscore(string $key, string $min, string $max)
 *
 * Transaction Commands:
 * @method static Connection multi()
 * @method static array|false exec()
 * @method static bool discard()
 * @method static array|false transaction(\Closure $callback)
 *
 * Lua Scripting:
 * @method static mixed eval(string $script, array $keys = [], array $args = [])
 * @method static mixed evalsha(string $sha, array $keys = [], array $args = [])
 *
 * Pub/Sub Commands:
 * @method static int publish(string $channel, string $message)
 * @method static void subscribe(array $channels, \Closure $callback)
 * @method static void psubscribe(array $patterns, \Closure $callback)
 *
 * Other Commands:
 * @method static bool ping()
 * @method static bool select(int $database)
 * @method static bool flushdb()
 *
 * @see \Ryra\Core\Redis\RedisManager
 * @see \Ryra\Core\Redis\Connections\Connection
 */
class Redis extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'redis';
    }
}
