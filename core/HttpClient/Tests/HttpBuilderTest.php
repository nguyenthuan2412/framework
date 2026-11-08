<?php

namespace Ryra\Core\HttpClient\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\HttpClient\Http;
use Ryra\Core\HttpClient\RedirectOption;
use Ryra\Core\HttpClient\Response;

class HttpBuilderTest extends TestCase
{
    private Http         $http;
    private TestableHttp $testable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->http     = new Http();
        $this->testable = new TestableHttp();
    }

    // =========================================================================
    // Helper: read protected property via reflection
    // =========================================================================

    private function prop(string $name): mixed
    {
        return (new \ReflectionProperty(Http::class, $name))->getValue($this->http);
    }

    // =========================================================================
    // Headers
    // =========================================================================

    public function test_with_header_sets_single_header(): void
    {
        $this->http->withHeader('X-Foo', 'bar');
        $this->assertSame('bar', $this->prop('headers')['X-Foo']);
    }

    public function test_with_header_returns_same_instance(): void
    {
        $result = $this->http->withHeader('X-Foo', 'bar');
        $this->assertSame($this->http, $result);
    }

    public function test_with_headers_sets_multiple(): void
    {
        $this->http->withHeaders(['X-A' => '1', 'X-B' => '2']);
        $headers = $this->prop('headers');
        $this->assertSame('1', $headers['X-A']);
        $this->assertSame('2', $headers['X-B']);
    }

    public function test_with_headers_does_not_remove_existing(): void
    {
        $this->http->withHeaders(['X-New' => 'val']);
        $this->assertArrayHasKey('Content-Type', $this->prop('headers'));
    }

    public function test_replace_headers_clears_existing(): void
    {
        $this->http->replaceHeaders(['X-Only' => 'one']);
        $headers = $this->prop('headers');
        $this->assertSame(['X-Only' => 'one'], $headers);
    }

    public function test_with_token_sets_bearer_authorization(): void
    {
        $this->http->withToken('my-token');
        $this->assertSame('Bearer my-token', $this->prop('headers')['Authorization']);
    }

    public function test_with_api_key_sets_x_api_key_header(): void
    {
        $this->http->withApiKey('secret-key');
        $this->assertSame('secret-key', $this->prop('headers')['X-Api-Key']);
    }

    public function test_as_json_sets_content_type(): void
    {
        $this->http->asForm()->asJson();
        $this->assertSame('application/json', $this->prop('headers')['Content-Type']);
    }

    public function test_as_form_sets_content_type(): void
    {
        $this->http->asForm();
        $this->assertSame('application/x-www-form-urlencoded', $this->prop('headers')['Content-Type']);
    }

    public function test_accept_sets_accept_header(): void
    {
        $this->http->accept('text/html');
        $this->assertSame('text/html', $this->prop('headers')['Accept']);
    }

    public function test_accept_json_sets_json_accept(): void
    {
        $this->http->acceptJson();
        $this->assertSame('application/json', $this->prop('headers')['Accept']);
    }

    // =========================================================================
    // Auth
    // =========================================================================

    public function test_with_basic_auth_sets_credentials(): void
    {
        $this->http->withBasicAuth('user', 'pass');
        $this->assertSame('user', $this->prop('username'));
        $this->assertSame('pass', $this->prop('password'));
    }

    public function test_username_password_are_null_by_default(): void
    {
        $this->assertNull($this->prop('username'));
        $this->assertNull($this->prop('password'));
    }

    // =========================================================================
    // Timeout / Config
    // =========================================================================

    public function test_timeout_sets_config_value(): void
    {
        $this->http->timeout(10.5);
        $this->assertSame(10.5, $this->prop('configs')['timeout']);
    }

    public function test_connect_timeout_sets_config_value(): void
    {
        $this->http->connectTimeout(3.0);
        $this->assertSame(3.0, $this->prop('configs')['connect_timeout']);
    }

    public function test_timeout_returns_same_instance(): void
    {
        $this->assertSame($this->http, $this->http->timeout(5));
    }

    public function test_default_timeout_is_30(): void
    {
        $this->assertSame(30, $this->prop('configs')['timeout']);
    }

    public function test_default_connect_timeout_is_5(): void
    {
        $this->assertSame(5, $this->prop('configs')['connect_timeout']);
    }

    // =========================================================================
    // Base URL
    // =========================================================================

    public function test_base_url_setter_stores_url(): void
    {
        $this->http->baseUrl('https://api.example.com/');
        $this->assertSame('https://api.example.com', $this->prop('baseUrl'));
    }

    public function test_base_url_trims_trailing_slash(): void
    {
        $this->http->baseUrl('https://api.example.com///');
        $this->assertSame('https://api.example.com', $this->prop('baseUrl'));
    }

    public function test_base_url_getter_returns_string_when_null_passed(): void
    {
        $this->http->baseUrl('https://api.example.com');
        $result = $this->http->baseUrl(null);
        $this->assertSame('https://api.example.com', $result);
    }

    public function test_base_url_is_empty_by_default(): void
    {
        $this->assertSame('', $this->prop('baseUrl'));
    }

    // =========================================================================
    // Query Params
    // =========================================================================

    public function test_with_params_merges_query_params(): void
    {
        $this->http->withParams(['page' => 1, 'limit' => 10]);
        $this->assertSame(['page' => 1, 'limit' => 10], $this->prop('query'));
    }

    public function test_with_params_merges_with_existing(): void
    {
        $this->http->withParams(['page' => 1]);
        $this->http->withParams(['limit' => 10]);
        $this->assertSame(['page' => 1, 'limit' => 10], $this->prop('query'));
    }

    public function test_with_params_overwrites_duplicate_keys(): void
    {
        $this->http->withParams(['page' => 1]);
        $this->http->withParams(['page' => 2]);
        $this->assertSame(['page' => 2], $this->prop('query'));
    }

    // =========================================================================
    // Retry
    // =========================================================================

    public function test_retry_sets_max_retries(): void
    {
        $this->http->retry(3);
        $this->assertSame(3, $this->prop('maxRetries'));
    }

    public function test_retry_sets_delay(): void
    {
        $this->http->retry(3, 500);
        $this->assertSame(500, $this->prop('retryDelay'));
    }

    public function test_retry_minimum_is_one(): void
    {
        $this->http->retry(0);
        $this->assertSame(1, $this->prop('maxRetries'));

        $this->http->retry(-5);
        $this->assertSame(1, $this->prop('maxRetries'));
    }

    public function test_retry_default_delay_is_zero(): void
    {
        $this->http->retry(3);
        $this->assertSame(0, $this->prop('retryDelay'));
    }

    public function test_retry_returns_same_instance(): void
    {
        $this->assertSame($this->http, $this->http->retry(3));
    }

    // =========================================================================
    // Redirect
    // =========================================================================

    public function test_allow_redirect_creates_redirect_option(): void
    {
        $this->http->allowRedirect();
        $option = $this->prop('redirectOption');
        $this->assertInstanceOf(RedirectOption::class, $option);
    }

    public function test_allow_redirect_defaults(): void
    {
        $this->http->allowRedirect();
        $option = $this->prop('redirectOption');
        $this->assertSame(5, $option->max);
        $this->assertFalse($option->strict);
        $this->assertFalse($option->referer);
        $this->assertSame(['http', 'https'], $option->protocols);
        $this->assertFalse($option->trackRedirects);
    }

    public function test_allow_redirect_custom_options(): void
    {
        $this->http->allowRedirect(max: 3, strict: true, referer: true, protocols: ['https']);
        $option = $this->prop('redirectOption');
        $this->assertSame(3, $option->max);
        $this->assertTrue($option->strict);
        $this->assertTrue($option->referer);
        $this->assertSame(['https'], $option->protocols);
    }

    public function test_redirect_option_is_null_by_default(): void
    {
        $this->assertNull($this->prop('redirectOption'));
    }

    public function test_on_redirect_sets_callback(): void
    {
        $cb = fn() => null;
        $this->http->allowRedirect()->onRedirect($cb);
        $this->assertSame($cb, $this->prop('redirectOption')->onRedirect);
    }

    public function test_on_redirect_auto_creates_redirect_option_if_not_set(): void
    {
        $this->http->onRedirect(fn() => null);
        $this->assertInstanceOf(RedirectOption::class, $this->prop('redirectOption'));
    }

    public function test_on_redirect_returns_same_instance(): void
    {
        $this->assertSame($this->http, $this->http->onRedirect(fn() => null));
    }

    // =========================================================================
    // File Attachments
    // =========================================================================

    public function test_attach_stores_file_info(): void
    {
        $this->http->attach('photo', '/tmp/photo.jpg', 'image/jpeg', 'photo.jpg');
        $files = $this->prop('files');
        $this->assertArrayHasKey('photo', $files);
        $this->assertSame('/tmp/photo.jpg', $files['photo'][0]);
        $this->assertSame('image/jpeg', $files['photo'][1]);
        $this->assertSame('photo.jpg', $files['photo'][2]);
    }

    public function test_attach_with_optional_params_defaults_to_empty_string(): void
    {
        $this->http->attach('file', '/tmp/file.txt');
        $files = $this->prop('files');
        $this->assertSame('', $files['file'][1]); // mimeType
        $this->assertSame('', $files['file'][2]); // fileName
        $this->assertSame(0, $files['file'][3]);   // offset
        $this->assertSame(0, $files['file'][4]);   // length
    }

    public function test_attach_multiple_files(): void
    {
        $this->http->attach('file1', '/tmp/a.jpg')->attach('file2', '/tmp/b.jpg');
        $files = $this->prop('files');
        $this->assertCount(2, $files);
        $this->assertArrayHasKey('file1', $files);
        $this->assertArrayHasKey('file2', $files);
    }

    public function test_attach_returns_same_instance(): void
    {
        $this->assertSame($this->http, $this->http->attach('f', '/tmp/f.txt'));
    }

    // =========================================================================
    // Fluent chain
    // =========================================================================

    public function test_fluent_chain_works(): void
    {
        $result = $this->http
            ->baseUrl('https://api.example.com')
            ->withToken('tok')
            ->acceptJson()
            ->timeout(15)
            ->retry(3, 200)
            ->allowRedirect(3);

        $this->assertSame($this->http, $result);
        $this->assertSame('https://api.example.com', $this->prop('baseUrl'));
        $this->assertSame('Bearer tok', $this->prop('headers')['Authorization']);
        $this->assertSame('application/json', $this->prop('headers')['Accept']);
        $this->assertSame(15.0, $this->prop('configs')['timeout']);
        $this->assertSame(3, $this->prop('maxRetries'));
        $this->assertSame(200, $this->prop('retryDelay'));
        $this->assertSame(3, $this->prop('redirectOption')->max);
    }

    // =========================================================================
    // HTTP method tests — dùng TestableHttp (xem cuối file)
    // =========================================================================

    // =========================================================================
    // HTTP Methods — verify correct method + url + params are forwarded
    // =========================================================================

    public function test_get_dispatches_get_method(): void
    {
        $this->testable->get('https://api.example.com/users');
        $this->assertSame('GET', $this->testable->lastMethod);
        $this->assertSame('https://api.example.com/users', $this->testable->lastUrl);
    }

    public function test_get_forwards_query_params(): void
    {
        $this->testable->get('https://api.example.com/users', ['page' => 2]);
        $this->assertSame(['page' => 2], $this->testable->lastParams);
    }

    public function test_post_dispatches_post_method(): void
    {
        $this->testable->post('https://api.example.com/users', ['name' => 'Tuan']);
        $this->assertSame('POST', $this->testable->lastMethod);
        $this->assertSame(['name' => 'Tuan'], $this->testable->lastParams);
    }

    public function test_put_dispatches_put_method(): void
    {
        $this->testable->put('https://api.example.com/users/1', ['name' => 'Tuan']);
        $this->assertSame('PUT', $this->testable->lastMethod);
    }

    public function test_patch_dispatches_patch_method(): void
    {
        $this->testable->patch('https://api.example.com/users/1', ['name' => 'Tuan']);
        $this->assertSame('PATCH', $this->testable->lastMethod);
    }

    public function test_delete_dispatches_delete_method(): void
    {
        $this->testable->delete('https://api.example.com/users/1');
        $this->assertSame('DELETE', $this->testable->lastMethod);
        $this->assertSame('https://api.example.com/users/1', $this->testable->lastUrl);
    }

    public function test_head_dispatches_head_method(): void
    {
        $this->testable->head('https://api.example.com/users');
        $this->assertSame('HEAD', $this->testable->lastMethod);
    }

    public function test_http_methods_return_response(): void
    {
        $this->assertInstanceOf(Response::class, $this->testable->get('https://api.example.com'));
        $this->assertInstanceOf(Response::class, $this->testable->post('https://api.example.com'));
        $this->assertInstanceOf(Response::class, $this->testable->put('https://api.example.com'));
        $this->assertInstanceOf(Response::class, $this->testable->patch('https://api.example.com'));
        $this->assertInstanceOf(Response::class, $this->testable->delete('https://api.example.com'));
        $this->assertInstanceOf(Response::class, $this->testable->head('https://api.example.com'));
    }

    public function test_builder_state_is_forwarded_to_request(): void
    {
        $this->testable
            ->withToken('secret')
            ->baseUrl('https://api.example.com')
            ->get('/users');

        $this->assertSame('GET', $this->testable->lastMethod);
        $this->assertSame('/users', $this->testable->lastUrl);
        // Verify builder state (token) was set before request
        $headers = (new \ReflectionProperty(Http::class, 'headers'))->getValue($this->testable);
        $this->assertSame('Bearer secret', $headers['Authorization']);
    }
}

// =============================================================================
// Test double: overrides sendRequest to capture calls without hitting network
// =============================================================================

class TestableHttp extends Http
{
    public string $lastUrl    = '';
    public string $lastMethod = '';
    public array  $lastParams = [];

    protected function sendRequest(string $url, string $method, iterable $params = []): Response
    {
        $this->lastUrl    = $url;
        $this->lastMethod = $method;
        $this->lastParams = is_array($params) ? $params : [...$params];

        $client = new \stdClass();
        $client->body       = '';
        $client->statusCode = 200;
        $client->headers    = [];

        return new Response($client);
    }
}
