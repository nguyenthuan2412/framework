<?php

namespace Ryra\Core\HttpClient\Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use Ryra\Core\HttpClient\Exception\RequestException;
use Ryra\Core\HttpClient\Response;

class ResponseTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeClient(string $body = '', int $statusCode = 200, array $headers = []): object
    {
        $client = new \stdClass();
        $client->body       = $body;
        $client->statusCode = $statusCode;
        $client->headers    = $headers;
        return $client;
    }

    private function response(string $body = '', int $statusCode = 200, array $headers = []): Response
    {
        return new Response($this->makeClient($body, $statusCode, $headers));
    }

    // =========================================================================
    // Body
    // =========================================================================

    public function test_body_returns_raw_string(): void
    {
        $r = $this->response('hello world');
        $this->assertSame('hello world', $r->body());
    }

    public function test_to_string_returns_body(): void
    {
        $r = $this->response('hello');
        $this->assertSame('hello', (string) $r);
    }

    public function test_get_swoole_client_returns_original_object(): void
    {
        $client = $this->makeClient('body', 200);
        $r = new Response($client);
        $this->assertSame($client, $r->getSwooleClient());
    }

    // =========================================================================
    // JSON
    // =========================================================================

    public function test_is_json_returns_true_for_valid_json(): void
    {
        $this->assertTrue($this->response('{"key":"value"}')->isJson());
        $this->assertTrue($this->response('[1,2,3]')->isJson());
        $this->assertTrue($this->response('"string"')->isJson());
    }

    public function test_is_json_returns_false_for_invalid_json(): void
    {
        $this->assertFalse($this->response('not json')->isJson());
        $this->assertFalse($this->response('')->isJson());
        $this->assertFalse($this->response('{broken')->isJson());
    }

    public function test_json_decodes_full_body(): void
    {
        $r = $this->response('{"name":"Tuan","age":25}');
        $this->assertSame(['name' => 'Tuan', 'age' => 25], $r->json());
    }

    public function test_json_with_top_level_key(): void
    {
        $r = $this->response('{"name":"Tuan","age":25}');
        $this->assertSame('Tuan', $r->json('name'));
        $this->assertSame(25, $r->json('age'));
    }

    public function test_json_with_nested_key_using_dot_notation(): void
    {
        $r = $this->response('{"user":{"address":{"city":"HCM"}}}');
        $this->assertSame('HCM', $r->json('user.address.city'));
    }

    public function test_json_returns_default_when_key_missing(): void
    {
        $r = $this->response('{"name":"Tuan"}');
        $this->assertNull($r->json('missing'));
        $this->assertSame('default', $r->json('missing', 'default'));
    }

    public function test_json_is_cached_across_calls(): void
    {
        $r = $this->response('{"x":1}');
        $first  = $r->json();
        $second = $r->json();
        $this->assertSame($first, $second);
    }

    public function test_object_returns_stdclass(): void
    {
        $r = $this->response('{"name":"Tuan"}');
        $obj = $r->object();
        $this->assertInstanceOf(\stdClass::class, $obj);
        $this->assertSame('Tuan', $obj->name);
    }

    public function test_collect_returns_collection_with_full_data(): void
    {
        $r = $this->response('[{"id":1},{"id":2}]');
        $col = $r->collect();
        $this->assertCount(2, $col);
    }

    public function test_collect_with_key_returns_nested_array(): void
    {
        $r = $this->response('{"data":[1,2,3]}');
        $col = $r->collect('data');
        $this->assertSame([1, 2, 3], $col->all());
    }

    // =========================================================================
    // Status Code
    // =========================================================================

    public function test_status_returns_code(): void
    {
        $this->assertSame(200, $this->response('', 200)->status());
        $this->assertSame(404, $this->response('', 404)->status());
        $this->assertSame(500, $this->response('', 500)->status());
    }

    // =========================================================================
    // Headers
    // =========================================================================

    public function test_headers_returns_all_headers(): void
    {
        $r = $this->response('', 200, ['Content-Type' => 'application/json', 'X-Foo' => 'bar']);
        $this->assertSame(['Content-Type' => 'application/json', 'X-Foo' => 'bar'], $r->headers());
    }

    public function test_header_returns_specific_header(): void
    {
        $r = $this->response('', 200, ['Content-Type' => 'application/json']);
        $this->assertSame('application/json', $r->header('Content-Type'));
    }

    public function test_header_returns_null_for_missing_key(): void
    {
        $r = $this->response('', 200, []);
        $this->assertNull($r->header('X-Missing'));
    }

    public function test_headers_returns_empty_array_when_null(): void
    {
        $client = new \stdClass();
        $client->body       = '';
        $client->statusCode = 200;
        $client->headers    = null;
        $r = new Response($client);
        $this->assertSame([], $r->headers());
    }

    // =========================================================================
    // 2xx Status Helpers
    // =========================================================================

    public function test_ok_is_true_for_200(): void
    {
        $this->assertTrue($this->response('', 200)->ok());
        $this->assertFalse($this->response('', 201)->ok());
    }

    public function test_successful_for_2xx_range(): void
    {
        foreach ([200, 201, 202, 204, 299] as $code) {
            $this->assertTrue($this->response('', $code)->successful(), "Expected successful for $code");
        }
        $this->assertFalse($this->response('', 300)->successful());
        $this->assertFalse($this->response('', 400)->successful());
    }

    public function test_created_is_true_for_201(): void
    {
        $this->assertTrue($this->response('', 201)->created());
        $this->assertFalse($this->response('', 200)->created());
    }

    public function test_accepted_is_true_for_202(): void
    {
        $this->assertTrue($this->response('', 202)->accepted());
        $this->assertFalse($this->response('', 200)->accepted());
    }

    public function test_no_content_is_true_for_204(): void
    {
        $this->assertTrue($this->response('', 204)->noContent());
        $this->assertFalse($this->response('', 200)->noContent());
    }

    // =========================================================================
    // 3xx Status Helpers
    // =========================================================================

    public function test_redirect_for_3xx_range(): void
    {
        foreach ([301, 302, 307, 308] as $code) {
            $this->assertTrue($this->response('', $code)->redirect(), "Expected redirect for $code");
        }
        $this->assertFalse($this->response('', 200)->redirect());
        $this->assertFalse($this->response('', 400)->redirect());
    }

    public function test_moved_permanently_is_true_for_301(): void
    {
        $this->assertTrue($this->response('', 301)->movedPermanently());
        $this->assertFalse($this->response('', 302)->movedPermanently());
    }

    public function test_found_is_true_for_302(): void
    {
        $this->assertTrue($this->response('', 302)->found());
        $this->assertFalse($this->response('', 301)->found());
    }

    // =========================================================================
    // 4xx Status Helpers
    // =========================================================================

    public function test_bad_request_is_true_for_400(): void
    {
        $this->assertTrue($this->response('', 400)->badRequest());
        $this->assertFalse($this->response('', 401)->badRequest());
    }

    public function test_unauthorized_is_true_for_401(): void
    {
        $this->assertTrue($this->response('', 401)->unauthorized());
    }

    public function test_payment_required_is_true_for_402(): void
    {
        $this->assertTrue($this->response('', 402)->paymentRequired());
    }

    public function test_forbidden_is_true_for_403(): void
    {
        $this->assertTrue($this->response('', 403)->forbidden());
    }

    public function test_not_found_is_true_for_404(): void
    {
        $this->assertTrue($this->response('', 404)->notFound());
    }

    public function test_request_timeout_is_true_for_408(): void
    {
        $this->assertTrue($this->response('', 408)->requestTimeout());
    }

    public function test_conflict_is_true_for_409(): void
    {
        $this->assertTrue($this->response('', 409)->conflict());
    }

    public function test_unprocessable_entity_is_true_for_422(): void
    {
        $this->assertTrue($this->response('', 422)->unprocessableEntity());
    }

    public function test_too_many_requests_is_true_for_429(): void
    {
        $this->assertTrue($this->response('', 429)->tooManyRequests());
    }

    public function test_client_error_for_4xx_range(): void
    {
        foreach ([400, 401, 403, 404, 422, 499] as $code) {
            $this->assertTrue($this->response('', $code)->clientError(), "Expected clientError for $code");
        }
        $this->assertFalse($this->response('', 200)->clientError());
        $this->assertFalse($this->response('', 500)->clientError());
    }

    // =========================================================================
    // 5xx Status Helpers
    // =========================================================================

    public function test_server_error_for_5xx_range(): void
    {
        foreach ([500, 502, 503, 504] as $code) {
            $this->assertTrue($this->response('', $code)->serverError(), "Expected serverError for $code");
        }
        $this->assertFalse($this->response('', 499)->serverError());
    }

    // =========================================================================
    // failed()
    // =========================================================================

    public function test_failed_is_true_for_4xx_and_5xx(): void
    {
        $this->assertTrue($this->response('', 400)->failed());
        $this->assertTrue($this->response('', 500)->failed());
        $this->assertFalse($this->response('', 200)->failed());
        $this->assertFalse($this->response('', 301)->failed());
    }

    // =========================================================================
    // throw() / throwIf()
    // =========================================================================

    public function test_throw_returns_self_when_response_is_successful(): void
    {
        $r = $this->response('', 200);
        $this->assertSame($r, $r->throw());
    }

    public function test_throw_throws_request_exception_on_4xx(): void
    {
        $this->expectException(RequestException::class);
        $this->response('', 404)->throw();
    }

    public function test_throw_throws_request_exception_on_5xx(): void
    {
        $this->expectException(RequestException::class);
        $this->response('', 500)->throw();
    }

    public function test_throw_exception_message_contains_status_code(): void
    {
        try {
            $this->response('', 422)->throw();
            $this->fail('Expected RequestException');
        } catch (RequestException $e) {
            $this->assertStringContainsString('422', $e->getMessage());
        }
    }

    public function test_throw_if_throws_when_condition_true_and_failed(): void
    {
        $this->expectException(RequestException::class);
        $this->response('', 500)->throwIf(true);
    }

    public function test_throw_if_does_not_throw_when_condition_false(): void
    {
        $r = $this->response('', 500);
        $this->assertSame($r, $r->throwIf(false));
    }

    public function test_throw_if_does_not_throw_when_successful_even_if_condition_true(): void
    {
        $r = $this->response('', 200);
        $this->assertSame($r, $r->throwIf(true));
    }

    // =========================================================================
    // ArrayAccess
    // =========================================================================

    public function test_offset_exists_returns_true_for_existing_key(): void
    {
        $r = $this->response('{"name":"Tuan"}');
        $this->assertTrue(isset($r['name']));
        $this->assertFalse(isset($r['missing']));
    }

    public function test_offset_get_returns_json_value(): void
    {
        $r = $this->response('{"id":42,"name":"Tuan"}');
        $this->assertSame(42, $r['id']);
        $this->assertSame('Tuan', $r['name']);
    }

    public function test_offset_set_throws_logic_exception(): void
    {
        $this->expectException(LogicException::class);
        $r = $this->response('{"name":"Tuan"}');
        $r['name'] = 'other';
    }

    public function test_offset_unset_throws_logic_exception(): void
    {
        $this->expectException(LogicException::class);
        $r = $this->response('{"name":"Tuan"}');
        unset($r['name']);
    }
}
