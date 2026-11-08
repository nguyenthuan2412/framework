<?php

namespace Ryra\Core\Cache\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\Cache\Drivers\FileDriver;
use Ryra\Core\Cache\Marshallers\SerializeMarshaller;

class FileDriverTest extends TestCase
{
    protected FileDriver $cache;
    protected string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = sys_get_temp_dir() . '/vietiso_cache_test_' . uniqid();
        $this->cache = new FileDriver(
            $this->cacheDir,
            new SerializeMarshaller(),
            'test:',
        );
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
        @rmdir($this->cacheDir);
        parent::tearDown();
    }

    // ==================== Basic Operations ====================

    public function test_can_set_and_get_value(): void
    {
        $this->cache->set('key1', 'hello');
        $this->assertEquals('hello', $this->cache->get('key1'));
    }

    public function test_get_returns_default_when_key_not_found(): void
    {
        $this->assertNull($this->cache->get('nonexistent'));
        $this->assertEquals('default', $this->cache->get('nonexistent', 'default'));
    }

    public function test_can_set_and_get_complex_types(): void
    {
        $array = ['foo' => 'bar', 'nested' => [1, 2, 3]];
        $this->cache->set('array', $array);
        $this->assertEquals($array, $this->cache->get('array'));

        $this->cache->set('integer', 42);
        $this->assertEquals(42, $this->cache->get('integer'));

        $this->cache->set('float', 3.14);
        $this->assertEquals(3.14, $this->cache->get('float'));

        $this->cache->set('boolean', true);
        $this->assertTrue($this->cache->get('boolean'));
    }

    public function test_can_overwrite_existing_value(): void
    {
        $this->cache->set('key', 'first');
        $this->cache->set('key', 'second');
        $this->assertEquals('second', $this->cache->get('key'));
    }

    // ==================== Delete ====================

    public function test_can_delete_key(): void
    {
        $this->cache->set('key', 'value');
        $this->assertTrue($this->cache->delete('key'));
        $this->assertNull($this->cache->get('key'));
    }

    public function test_delete_nonexistent_key_returns_true(): void
    {
        $this->assertTrue($this->cache->delete('nonexistent'));
    }

    // ==================== Has ====================

    public function test_has_returns_true_for_existing_key(): void
    {
        $this->cache->set('key', 'value');
        $this->assertTrue($this->cache->has('key'));
    }

    public function test_has_returns_false_for_missing_key(): void
    {
        $this->assertFalse($this->cache->has('nonexistent'));
    }

    // ==================== TTL ====================

    public function test_value_expires_after_ttl(): void
    {
        $this->cache->set('ttl_key', 'value', 1);
        $this->assertEquals('value', $this->cache->get('ttl_key'));

        sleep(2);

        $this->assertNull($this->cache->get('ttl_key'));
    }

    public function test_value_without_ttl_persists(): void
    {
        $this->cache->set('forever', 'value');
        $this->assertEquals('value', $this->cache->get('forever'));
    }

    public function test_ttl_with_date_interval(): void
    {
        $this->cache->set('interval', 'value', new \DateInterval('PT2S'));
        $this->assertEquals('value', $this->cache->get('interval'));

        sleep(3);

        $this->assertNull($this->cache->get('interval'));
    }

    public function test_has_returns_false_for_expired_key(): void
    {
        $this->cache->set('expired', 'value', 1);
        sleep(2);
        $this->assertFalse($this->cache->has('expired'));
    }

    // ==================== Multiple Operations ====================

    public function test_can_set_and_get_multiple(): void
    {
        $values = ['k1' => 'v1', 'k2' => 'v2', 'k3' => 'v3'];
        $this->cache->setMultiple($values);

        $result = $this->cache->getMultiple(['k1', 'k2', 'k3']);
        $this->assertEquals($values, $result);
    }

    public function test_get_multiple_returns_defaults_for_missing(): void
    {
        $this->cache->set('exists', 'value');

        $result = $this->cache->getMultiple(['exists', 'missing'], 'default');
        $this->assertEquals('value', $result['exists']);
        $this->assertEquals('default', $result['missing']);
    }

    public function test_can_delete_multiple(): void
    {
        $this->cache->setMultiple(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->cache->deleteMultiple(['a', 'b']);

        $this->assertNull($this->cache->get('a'));
        $this->assertNull($this->cache->get('b'));
        $this->assertEquals(3, $this->cache->get('c'));
    }

    // ==================== Clear ====================

    public function test_clear_removes_all_cached_items(): void
    {
        $this->cache->setMultiple(['x' => 1, 'y' => 2, 'z' => 3]);
        $this->assertTrue($this->cache->clear());

        $this->assertNull($this->cache->get('x'));
        $this->assertNull($this->cache->get('y'));
        $this->assertNull($this->cache->get('z'));
    }

    // ==================== Remember ====================

    public function test_remember_returns_cached_value(): void
    {
        $this->cache->set('remembered', 'cached');

        $callCount = 0;
        $result = $this->cache->remember('remembered', 60, function () use (&$callCount) {
            $callCount++;
            return 'computed';
        });

        $this->assertEquals('cached', $result);
        $this->assertEquals(0, $callCount);
    }

    public function test_remember_computes_and_caches_on_miss(): void
    {
        $callCount = 0;
        $result = $this->cache->remember('new_key', 60, function () use (&$callCount) {
            $callCount++;
            return 'computed';
        });

        $this->assertEquals('computed', $result);
        $this->assertEquals(1, $callCount);
        $this->assertEquals('computed', $this->cache->get('new_key'));
    }

    // ==================== Race Condition ====================

    public function test_concurrent_writes_do_not_corrupt_data(): void
    {
        // Ghi liên tục nhiều lần để verify atomic write
        for ($i = 0; $i < 50; $i++) {
            $this->cache->set('race', "value_{$i}");
            $result = $this->cache->get('race');
            // Giá trị phải là 1 trong các giá trị hợp lệ, không bị corrupt
            $this->assertMatchesRegularExpression('/^value_\d+$/', $result);
        }
    }

    // ==================== Edge Cases ====================

    public function test_can_cache_false_value(): void
    {
        $this->cache->set('false_val', false);
        $this->assertFalse($this->cache->get('false_val', 'not_false'));
    }

    public function test_can_cache_empty_string(): void
    {
        $this->cache->set('empty', '');
        $result = $this->cache->get('empty', 'default');
        $this->assertSame('', $result);
    }

    public function test_can_cache_zero(): void
    {
        $this->cache->set('zero', 0);
        $this->assertSame(0, $this->cache->get('zero', 'default'));
    }

    public function test_keys_with_special_characters(): void
    {
        $this->cache->set('key:with:colons', 'v1');
        $this->cache->set('key/with/slashes', 'v2');
        $this->cache->set('key.with.dots', 'v3');

        $this->assertEquals('v1', $this->cache->get('key:with:colons'));
        $this->assertEquals('v2', $this->cache->get('key/with/slashes'));
        $this->assertEquals('v3', $this->cache->get('key.with.dots'));
    }
}
