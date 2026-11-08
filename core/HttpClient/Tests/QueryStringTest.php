<?php

namespace Ryra\Core\HttpClient\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\HttpClient\Http;
use Swoole\Coroutine\Http\Client;

/**
 * Query string viết sẵn trong URL phải tới được server với mọi HTTP method.
 *
 * Trước đây chỉ GET/HEAD được gộp query, nên POST tới ".../abc?alt=sse" bị mất
 * hẳn phần "?alt=sse". Lỗi này chỉ lộ ra khi gọi API thật: Gemini trả về JSON
 * gộp thay vì SSE nên phần streaming im lặng không nhả ra chữ nào.
 */
class QueryStringTest extends TestCase
{
    public function test_giu_query_cua_url_khi_post(): void
    {
        $http = new QueryCapturingHttp();
        $http->post('https://example.com/v1/models/abc:stream?alt=sse', ['a' => 1]);

        $this->assertStringContainsString('alt=sse', $http->executedPath);
    }

    public function test_giu_query_khi_put_va_delete(): void
    {
        $http = new QueryCapturingHttp();
        $http->put('https://example.com/points?wait=true', []);
        $this->assertStringContainsString('wait=true', $http->executedPath);

        $http = new QueryCapturingHttp();
        $http->delete('https://example.com/points?force=1', []);
        $this->assertStringContainsString('force=1', $http->executedPath);
    }

    public function test_gop_query_cua_url_va_withParams(): void
    {
        $http = new QueryCapturingHttp();
        $http->withParams(['page' => 2])->post('https://example.com/list?alt=sse', []);

        $this->assertStringContainsString('alt=sse', $http->executedPath);
        $this->assertStringContainsString('page=2', $http->executedPath);
    }

    public function test_get_van_dua_params_vao_query_nhu_cu(): void
    {
        $http = new QueryCapturingHttp();
        $http->get('https://example.com/search?alt=sse', ['q' => 'tour']);

        $this->assertStringContainsString('alt=sse', $http->executedPath);
        $this->assertStringContainsString('q=tour', $http->executedPath);
    }

    public function test_khong_them_dau_hoi_khi_url_khong_co_query(): void
    {
        $http = new QueryCapturingHttp();
        $http->post('https://example.com/plain', ['a' => 1]);

        $this->assertSame('/plain', $http->executedPath);
    }
}

/**
 * Chặn ở tầng client của Swoole để đọc được đường dẫn thật sự gửi đi.
 */
class QueryCapturingHttp extends Http
{
    public string $executedPath = '';

    protected function buildSwooleClient(string $host, int $port, bool $ssl): Client
    {
        return new FakeSwooleClient($this, $host, $port, $ssl);
    }
}

class FakeSwooleClient extends Client
{
    public function __construct(
        protected QueryCapturingHttp $recorder,
        string $host,
        int $port = 80,
        bool $ssl = false,
    ) {
        $this->statusCode = 200;
        $this->body = '';
        $this->headers = [];
    }

    public function set(array $settings): bool
    {
        return true;
    }

    public function setMethod(string $method): bool
    {
        return true;
    }

    public function setHeaders(array $headers): bool
    {
        return true;
    }

    public function setData(mixed $data): bool
    {
        return true;
    }

    public function execute(string $path): bool
    {
        $this->recorder->executedPath = $path;

        return true;
    }

    public function close(): bool
    {
        return true;
    }
}
