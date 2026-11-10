<?php

namespace Ryra\Core\Http\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\Http\JsonResponse;
use Ryra\Core\Http\Response;
use Ryra\Core\Http\ResponseHeaderBag;

class ResponseHeaderBagTest extends TestCase
{
    /**
     * Toàn bộ property của bag (kể cả protected/private) để so sánh trạng thái.
     */
    private function state(ResponseHeaderBag $bag): array
    {
        return (fn() => \get_object_vars($this))->call($bag);
    }

    /**
     * Đường chậm: chạy logic gốc (replace([]) set Cache-Control rỗng rồi tạo Date) trên bag rỗng.
     */
    private function slowEmptyBag(): ResponseHeaderBag
    {
        $bag = (new \ReflectionClass(ResponseHeaderBag::class))->newInstanceWithoutConstructor();
        $bag->replace([]);
        return $bag;
    }

    public function test_fast_empty_constructor_matches_slow_path(): void
    {
        do {
            $second = \time();
            $fast = new ResponseHeaderBag();
            $slow = $this->slowEmptyBag();
        } while ($second !== \time()); // tránh lệch header Date khi vừa qua giây mới

        $this->assertSame($this->state($slow), $this->state($fast));
        $this->assertSame(['no-cache, private'], $fast->all('cache-control'));
        $this->assertTrue($fast->hasCacheControlDirective('no-cache'));
        $this->assertTrue($fast->hasCacheControlDirective('private'));
        $this->assertMatchesRegularExpression('/^\w{3}, \d{2} \w{3} \d{4} \d{2}:\d{2}:\d{2} GMT$/', $fast->get('date'));
    }

    public function test_fast_bag_behaves_like_slow_bag_after_changes(): void
    {
        $fast = new ResponseHeaderBag();
        $slow = $this->slowEmptyBag();
        foreach ([$fast, $slow] as $bag) {
            $bag->set('Content-Type', 'text/plain');
            $bag->set('Cache-Control', 'max-age=60');
            $bag->set('ETag', '"x"');
        }

        $this->assertSame($slow->all('cache-control'), $fast->all('cache-control'));
        $this->assertSame($slow->allPreserveCase()['Content-Type'], $fast->allPreserveCase()['Content-Type']);
        $this->assertSame('max-age=60, private', $fast->get('cache-control'));
    }

    public function test_bags_are_independent(): void
    {
        $a = new ResponseHeaderBag();
        $b = new ResponseHeaderBag();
        $a->set('Cache-Control', 'public, max-age=10');
        $a->set('X-Test', '1');

        $this->assertSame('no-cache, private', $b->get('cache-control'));
        $this->assertFalse($b->has('x-test'));
        $this->assertFalse($b->hasCacheControlDirective('public'));
    }

    public function test_constructor_with_headers_uses_original_path(): void
    {
        $bag = new ResponseHeaderBag(['X-Foo' => 'bar', 'Cache-Control' => 'public']);

        $this->assertSame('bar', $bag->get('x-foo'));
        $this->assertSame('public', $bag->get('cache-control'));
        $this->assertTrue($bag->has('date'));
    }

    public function test_responses_keep_expected_headers(): void
    {
        $text = Response::text('hi');
        $this->assertSame('text/plain; charset=utf-8', $text->headers->get('content-type'));
        $this->assertSame('no-cache, private', $text->headers->get('cache-control'));

        $json = new JsonResponse(['a' => 1]);
        $this->assertSame('application/json', $json->headers->get('content-type'));
        $this->assertSame('{"a":1}', $json->getContent());
    }
}
