<?php

namespace Ryra\Core\Redis\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\Config\Repository;
use Ryra\Core\Pool\PoolManager;
use Ryra\Core\Redis\RedisManager;

class RedisTest extends TestCase
{
    protected RedisManager $redis;
    protected object $conn;

    protected function setUp(): void
    {
        parent::setUp();
        $config = new Repository();
        $config->set('database', [
            'redis' => [
                'client' => 'predis',
                'options' => [
                    'prefix' => ''
                ],

                'default' => [
                    'host' => 'dragonfly',
                    'port' => (int) 6379,
                    'password' => null,
                    'database' => 0,
                    'read_timeout' => 60.0,
                    'timeout' => 0.0,
                    'persistent' => false
                ],
            ] 
        ]);
        $this->redis = new RedisManager(
            $config,
            new PoolManager(),
        );
        $this->conn = $this->redis->connection();
    }

    protected function tearDown(): void
    {
        $keys = $this->conn->keys('test:*');
        if (!empty($keys)) {
            $this->conn->del(...$keys);
        }
        parent::tearDown();
    }

    // ==================== Connection ====================

    public function test_can_connect_to_redis(): void
    {
        $this->assertTrue($this->conn->ping());
    }

    public function test_can_get_redis_manager_from_container(): void
    {
        $this->assertInstanceOf(RedisManager::class, $this->redis);
    }

    // ==================== String Commands ====================

    public function test_can_set_and_get_value(): void
    {
        $this->conn->set('test:key1', 'hello');
        $this->assertEquals('hello', $this->conn->get('test:key1'));
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        $this->assertNull($this->conn->get('test:nonexistent'));
    }

    public function test_can_setnx(): void
    {
        $this->assertTrue($this->conn->setnx('test:nx', 'first'));
        $this->assertFalse($this->conn->setnx('test:nx', 'second'));
        $this->assertEquals('first', $this->conn->get('test:nx'));
    }

    public function test_can_delete_key(): void
    {
        $this->conn->set('test:del', 'value');
        $this->conn->del('test:del');
        $this->assertNull($this->conn->get('test:del'));
    }

    public function test_can_check_key_exists(): void
    {
        $this->conn->set('test:exists', 'value');
        $this->assertTrue($this->conn->exists('test:exists'));
        $this->assertFalse($this->conn->exists('test:not_exists'));
    }

    public function test_can_set_multiple_values(): void
    {
        $this->conn->mset([
            'test:m1' => 'value1',
            'test:m2' => 'value2',
        ]);
        $values = $this->conn->mget(['test:m1', 'test:m2']);
        $this->assertEquals(['value1', 'value2'], $values);
    }

    public function test_can_set_value_with_expiration(): void
    {
        $this->conn->setex('test:ttl', 60, 'expires');
        $ttl = $this->conn->ttl('test:ttl');
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(60, $ttl);
    }

    public function test_can_increment_value(): void
    {
        $this->conn->set('test:counter', 10);
        $this->assertEquals(11, $this->conn->incr('test:counter'));
        $this->assertEquals(16, $this->conn->incrby('test:counter', 5));
    }

    public function test_can_decrement_value(): void
    {
        $this->conn->set('test:counter', 10);
        $this->assertEquals(9, $this->conn->decr('test:counter'));
        $this->assertEquals(5, $this->conn->decrby('test:counter', 4));
    }

    public function test_can_incrbyfloat(): void
    {
        $this->conn->set('test:float', '10.5');
        $result = $this->conn->incrbyfloat('test:float', 1.5);
        $this->assertEquals(12.0, $result);
    }

    public function test_can_append_and_strlen(): void
    {
        $this->conn->set('test:str', 'hello');
        $this->conn->append('test:str', ' world');
        $this->assertEquals('hello world', $this->conn->get('test:str'));
        $this->assertEquals(11, $this->conn->strlen('test:str'));
    }

    // ==================== Key Commands ====================

    public function test_can_expire_and_persist(): void
    {
        $this->conn->set('test:persist', 'value');
        $this->conn->expire('test:persist', 60);
        $this->assertGreaterThan(0, $this->conn->ttl('test:persist'));

        $this->conn->persist('test:persist');
        $this->assertEquals(-1, $this->conn->ttl('test:persist'));
    }

    public function test_can_pexpire_and_pttl(): void
    {
        $this->conn->set('test:pttl', 'value');
        $this->conn->pexpire('test:pttl', 5000);
        $pttl = $this->conn->pttl('test:pttl');
        $this->assertGreaterThan(0, $pttl);
        $this->assertLessThanOrEqual(5000, $pttl);
    }

    public function test_can_get_key_type(): void
    {
        $this->conn->set('test:type_str', 'value');
        $this->assertEquals('string', $this->conn->type('test:type_str'));

        $this->conn->lpush('test:type_list', 'a');
        $this->assertEquals('list', $this->conn->type('test:type_list'));

        $this->conn->sadd('test:type_set', 'a');
        $this->assertEquals('set', $this->conn->type('test:type_set'));

        $this->conn->zadd('test:type_zset', 1.0, 'a');
        $this->assertEquals('zset', $this->conn->type('test:type_zset'));

        $this->conn->hset('test:type_hash', 'f', 'v');
        $this->assertEquals('hash', $this->conn->type('test:type_hash'));
    }

    public function test_type_returns_none_for_missing_key(): void
    {
        $this->assertEquals('none', $this->conn->type('test:no_such_key'));
    }

    public function test_can_rename_key(): void
    {
        $this->conn->set('test:old_name', 'value');
        $this->conn->rename('test:old_name', 'test:new_name');
        $this->assertNull($this->conn->get('test:old_name'));
        $this->assertEquals('value', $this->conn->get('test:new_name'));
    }

    public function test_renamenx_only_if_not_exists(): void
    {
        $this->conn->set('test:rnx_src', 'value');
        $this->conn->set('test:rnx_dst', 'existing');
        $this->assertFalse($this->conn->renamenx('test:rnx_src', 'test:rnx_dst'));
        $this->assertEquals('value', $this->conn->get('test:rnx_src'));
    }

    // ==================== Hash Commands ====================

    public function test_can_set_and_get_hash_field(): void
    {
        $this->conn->hset('test:hash', 'field1', 'value1');
        $this->assertEquals('value1', $this->conn->hget('test:hash', 'field1'));
    }

    public function test_can_set_multiple_hash_fields(): void
    {
        $this->conn->hmset('test:hash', ['f1' => 'v1', 'f2' => 'v2']);
        $all = $this->conn->hgetall('test:hash');
        $this->assertArrayHasKey('f1', $all);
        $this->assertArrayHasKey('f2', $all);
    }

    public function test_can_hmget(): void
    {
        $this->conn->hmset('test:hash', ['a' => '1', 'b' => '2', 'c' => '3']);
        $result = $this->conn->hmget('test:hash', ['a', 'c']);
        $this->assertEquals('1', $result['a']);
        $this->assertEquals('3', $result['c']);
    }

    public function test_can_hincrby(): void
    {
        $this->conn->hset('test:hash', 'count', '10');
        $this->assertEquals(15, $this->conn->hincrby('test:hash', 'count', 5));
    }

    public function test_can_hincrbyfloat(): void
    {
        $this->conn->hset('test:hash', 'price', '10.5');
        $result = $this->conn->hincrbyfloat('test:hash', 'price', 2.3);
        $this->assertEqualsWithDelta(12.8, $result, 0.001);
    }

    public function test_can_hlen_hkeys_hvals(): void
    {
        $this->conn->hmset('test:hash', ['a' => '1', 'b' => '2', 'c' => '3']);
        $this->assertEquals(3, $this->conn->hlen('test:hash'));
        $this->assertCount(3, $this->conn->hkeys('test:hash'));
        $this->assertCount(3, $this->conn->hvals('test:hash'));
    }

    public function test_can_hsetnx(): void
    {
        $this->assertTrue($this->conn->hsetnx('test:hash', 'field', 'first'));
        $this->assertFalse($this->conn->hsetnx('test:hash', 'field', 'second'));
        $this->assertEquals('first', $this->conn->hget('test:hash', 'field'));
    }

    public function test_can_hexists_and_hdel(): void
    {
        $this->conn->hset('test:hash', 'field', 'value');
        $this->assertTrue($this->conn->hexists('test:hash', 'field'));
        $this->conn->hdel('test:hash', 'field');
        $this->assertFalse($this->conn->hexists('test:hash', 'field'));
    }

    // ==================== List Commands ====================

    public function test_can_push_and_pop_from_list(): void
    {
        $this->conn->lpush('test:list', 'a', 'b', 'c');
        $this->assertEquals(3, $this->conn->llen('test:list'));
        $this->conn->rpush('test:list', 'z');
        $this->assertEquals(4, $this->conn->llen('test:list'));
    }

    public function test_can_lpop_and_rpop(): void
    {
        $this->conn->rpush('test:list', 'a', 'b', 'c');
        $this->assertEquals('a', $this->conn->lpop('test:list'));
        $this->assertEquals('c', $this->conn->rpop('test:list'));
    }

    public function test_can_lrange(): void
    {
        $this->conn->rpush('test:list', 'a', 'b', 'c', 'd');
        $range = $this->conn->lrange('test:list', 1, 2);
        $this->assertEquals(['b', 'c'], $range);
    }

    public function test_can_lindex_and_lset(): void
    {
        $this->conn->rpush('test:list', 'a', 'b', 'c');
        $this->assertEquals('b', $this->conn->lindex('test:list', 1));

        $this->conn->lset('test:list', 1, 'B');
        $this->assertEquals('B', $this->conn->lindex('test:list', 1));
    }

    public function test_can_lrem(): void
    {
        $this->conn->rpush('test:list', 'a', 'b', 'a', 'c', 'a');
        $removed = $this->conn->lrem('test:list', 2, 'a');
        $this->assertEquals(2, $removed);
        $this->assertEquals(3, $this->conn->llen('test:list'));
    }

    // ==================== Set Commands ====================

    public function test_can_add_and_check_set_members(): void
    {
        $this->conn->sadd('test:set', 'a', 'b', 'c');
        $this->assertEquals(3, $this->conn->scard('test:set'));
        $this->assertTrue($this->conn->sismember('test:set', 'a'));
        $this->assertFalse($this->conn->sismember('test:set', 'z'));
    }

    public function test_can_srem(): void
    {
        $this->conn->sadd('test:set', 'a', 'b', 'c');
        $this->conn->srem('test:set', 'b');
        $this->assertEquals(2, $this->conn->scard('test:set'));
        $this->assertFalse($this->conn->sismember('test:set', 'b'));
    }

    public function test_can_smembers(): void
    {
        $this->conn->sadd('test:set', 'x', 'y', 'z');
        $members = $this->conn->smembers('test:set');
        sort($members);
        $this->assertEquals(['x', 'y', 'z'], $members);
    }

    public function test_can_sunion(): void
    {
        $this->conn->sadd('test:set1', 'a', 'b');
        $this->conn->sadd('test:set2', 'b', 'c');
        $union = $this->conn->sunion('test:set1', 'test:set2');
        sort($union);
        $this->assertEquals(['a', 'b', 'c'], $union);
    }

    public function test_can_sinter(): void
    {
        $this->conn->sadd('test:set1', 'a', 'b', 'c');
        $this->conn->sadd('test:set2', 'b', 'c', 'd');
        $inter = $this->conn->sinter('test:set1', 'test:set2');
        sort($inter);
        $this->assertEquals(['b', 'c'], $inter);
    }

    public function test_can_sdiff(): void
    {
        $this->conn->sadd('test:set1', 'a', 'b', 'c');
        $this->conn->sadd('test:set2', 'b', 'c', 'd');
        $diff = $this->conn->sdiff('test:set1', 'test:set2');
        $this->assertEquals(['a'], $diff);
    }

    public function test_can_spop(): void
    {
        $this->conn->sadd('test:set', 'a', 'b', 'c');
        $popped = $this->conn->spop('test:set');
        $this->assertNotNull($popped);
        $this->assertEquals(2, $this->conn->scard('test:set'));
    }

    public function test_can_srandmember(): void
    {
        $this->conn->sadd('test:set', 'a', 'b', 'c');
        $member = $this->conn->srandmember('test:set');
        $this->assertContains($member, ['a', 'b', 'c']);
        // srandmember không xóa member
        $this->assertEquals(3, $this->conn->scard('test:set'));
    }

    // ==================== Sorted Set Commands ====================

    public function test_can_zadd_and_zscore(): void
    {
        $this->conn->zadd('test:zset', 1.5, 'a');
        $this->conn->zadd('test:zset', 2.5, 'b');
        $this->assertEquals(1.5, $this->conn->zscore('test:zset', 'a'));
        $this->assertEquals(2.5, $this->conn->zscore('test:zset', 'b'));
        $this->assertNull($this->conn->zscore('test:zset', 'z'));
    }

    public function test_can_zrem(): void
    {
        $this->conn->zadd('test:zset', 1.0, 'a');
        $this->conn->zadd('test:zset', 2.0, 'b');
        $this->conn->zrem('test:zset', 'a');
        $this->assertEquals(1, $this->conn->zcard('test:zset'));
    }

    public function test_can_zrank_and_zrevrank(): void
    {
        $this->conn->zadd('test:zset', 1.0, 'a');
        $this->conn->zadd('test:zset', 2.0, 'b');
        $this->conn->zadd('test:zset', 3.0, 'c');

        $this->assertEquals(0, $this->conn->zrank('test:zset', 'a'));
        $this->assertEquals(2, $this->conn->zrank('test:zset', 'c'));
        $this->assertEquals(0, $this->conn->zrevrank('test:zset', 'c'));
        $this->assertNull($this->conn->zrank('test:zset', 'z'));
    }

    public function test_can_zrange_and_zrevrange(): void
    {
        $this->conn->zadd('test:zset', 1.0, 'a');
        $this->conn->zadd('test:zset', 2.0, 'b');
        $this->conn->zadd('test:zset', 3.0, 'c');

        $this->assertEquals(['a', 'b', 'c'], $this->conn->zrange('test:zset', 0, -1));
        $this->assertEquals(['c', 'b', 'a'], $this->conn->zrevrange('test:zset', 0, -1));
    }

    public function test_can_zrange_with_scores(): void
    {
        $this->conn->zadd('test:zset', 1.0, 'a');
        $this->conn->zadd('test:zset', 2.0, 'b');

        $result = $this->conn->zrange('test:zset', 0, -1, true);
        $this->assertEquals(['a' => 1.0, 'b' => 2.0], $result);
    }

    public function test_can_zrangebyscore(): void
    {
        $this->conn->zadd('test:zset', 1.0, 'a');
        $this->conn->zadd('test:zset', 2.0, 'b');
        $this->conn->zadd('test:zset', 3.0, 'c');
        $this->conn->zadd('test:zset', 4.0, 'd');

        $result = $this->conn->zrangebyscore('test:zset', '2', '3');
        $this->assertEquals(['b', 'c'], $result);
    }

    public function test_can_zrevrangebyscore(): void
    {
        $this->conn->zadd('test:zset', 1.0, 'a');
        $this->conn->zadd('test:zset', 2.0, 'b');
        $this->conn->zadd('test:zset', 3.0, 'c');

        $result = $this->conn->zrevrangebyscore('test:zset', '3', '1');
        $this->assertEquals(['c', 'b', 'a'], $result);
    }

    public function test_can_zcard_and_zcount(): void
    {
        $this->conn->zadd('test:zset', 1.0, 'a');
        $this->conn->zadd('test:zset', 2.0, 'b');
        $this->conn->zadd('test:zset', 3.0, 'c');

        $this->assertEquals(3, $this->conn->zcard('test:zset'));
        $this->assertEquals(2, $this->conn->zcount('test:zset', '1', '2'));
    }

    public function test_can_zincrby(): void
    {
        $this->conn->zadd('test:zset', 5.0, 'a');
        $newScore = $this->conn->zincrby('test:zset', 3.0, 'a');
        $this->assertEquals(8.0, $newScore);
    }

    public function test_can_zremrangebyrank(): void
    {
        $this->conn->zadd('test:zset', 1.0, 'a');
        $this->conn->zadd('test:zset', 2.0, 'b');
        $this->conn->zadd('test:zset', 3.0, 'c');

        $removed = $this->conn->zremrangebyrank('test:zset', 0, 1);
        $this->assertEquals(2, $removed);
        $this->assertEquals(1, $this->conn->zcard('test:zset'));
        $this->assertEquals(['c'], $this->conn->zrange('test:zset', 0, -1));
    }

    public function test_can_zremrangebyscore(): void
    {
        $this->conn->zadd('test:zset', 1.0, 'a');
        $this->conn->zadd('test:zset', 2.0, 'b');
        $this->conn->zadd('test:zset', 3.0, 'c');

        $removed = $this->conn->zremrangebyscore('test:zset', '1', '2');
        $this->assertEquals(2, $removed);
        $this->assertEquals(['c'], $this->conn->zrange('test:zset', 0, -1));
    }

    // ==================== Transaction Commands ====================

    public function test_can_multi_exec(): void
    {
        $this->conn->multi();
        $this->conn->set('test:tx1', 'val1');
        $this->conn->set('test:tx2', 'val2');
        $results = $this->conn->exec();

        $this->assertIsArray($results);
        $this->assertEquals('val1', $this->conn->get('test:tx1'));
        $this->assertEquals('val2', $this->conn->get('test:tx2'));
    }

    public function test_can_discard_transaction(): void
    {
        $this->conn->set('test:before', 'original');
        $this->conn->multi();
        $this->conn->set('test:before', 'changed');
        $this->conn->discard();

        $this->assertEquals('original', $this->conn->get('test:before'));
    }

    public function test_can_use_transaction_callback(): void
    {
        $results = $this->conn->transaction(function ($conn) {
            $conn->set('test:cb1', 'value1');
            $conn->set('test:cb2', 'value2');
        });

        $this->assertIsArray($results);
        $this->assertEquals('value1', $this->conn->get('test:cb1'));
        $this->assertEquals('value2', $this->conn->get('test:cb2'));
    }

    public function test_transaction_callback_rolls_back_on_exception(): void
    {
        $this->conn->set('test:safe', 'untouched');

        try {
            $this->conn->transaction(function ($conn) {
                $conn->set('test:safe', 'changed');
                throw new \RuntimeException('Fail');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        // MULTI/EXEC transaction trong Redis không rollback data đã queue,
        // nhưng DISCARD sẽ hủy tất cả commands trong queue
        $this->assertEquals('untouched', $this->conn->get('test:safe'));
    }

    // ==================== Lua Scripting ====================

    public function test_can_eval_lua_script(): void
    {
        $result = $this->conn->eval("return 'hello'");
        $this->assertEquals('hello', $result);
    }

    public function test_can_eval_lua_with_keys_and_args(): void
    {
        $script = "redis.call('SET', KEYS[1], ARGV[1]) return redis.call('GET', KEYS[1])";
        $result = $this->conn->eval($script, ['test:lua'], ['lua_value']);
        $this->assertEquals('lua_value', $result);
    }

    // ==================== Pub/Sub Commands ====================

    public function test_can_publish(): void
    {
        // Publish trả về số subscribers nhận được (0 nếu không có ai subscribe)
        $count = $this->conn->publish('test:channel', 'hello');
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function test_subscriber_creates_dedicated_connection(): void
    {
        $subscriber = $this->redis->subscriber();
        $this->assertNotSame($this->conn, $subscriber);
        $this->assertTrue($subscriber->ping());
        $subscriber->disconnect();
    }
}
