<?php

namespace Ryra\Core\HttpClient;

use Ryra\Core\HttpClient\Exception\InvalidUrlException;
use Ryra\Core\Contracts\HttpClient\Response;
use Ryra\Core\Contracts\Pool\PoolOption;
use Ryra\Core\Macro\Macroable;
use Ryra\Core\Url\Url;
use Ryra\Core\App;
use Ryra\Core\Collection\Collection;
use Ryra\Core\HttpClient\Response as HttpClientResponse;
use Ryra\Core\Support\Arrayable;
use Swoole\Coroutine\Http\Client;
use Swoole\Coroutine\Http2\Client as Http2Client;

class Http implements \Ryra\Core\Contracts\HttpClient\Http
{
    use Macroable;

    protected array $headers = [
        'Content-Type' => 'application/json'
    ];

    protected array $query = [];

    protected ?string $username = null;

    protected ?string $password = null;

    protected array $configs = [
        'timeout' => 30,
        'connect_timeout' => 5,
    ];

    protected float $version = 1.1;

    protected int $maxRetries = 1;

    protected int $retryDelay = 0;

    protected array $files = [];

    protected string $baseUrl = '';

    protected ?RedirectOption $redirectOption = null;

    /**
     * Handler nhận từng mảnh body ngay khi socket đọc được (SSE, chunked download).
     *
     * @var (callable(string):void)|null
     */
    protected $chunkHandler = null;

    /**
     * Người dùng đã tự chỉ định timeout hay chưa - dùng để biết có được phép
     * bỏ timeout khi stream hay không.
     */
    protected bool $timeoutOverridden = false;

    public function pool(string $name, PoolOption|array|null $options = null): ?static
    {
        $poolManager = App::service('pool');
        if (func_num_args() == 1) {
            $pool = $poolManager->get($name);
            if ($pool === null) {
                throw new \RuntimeException("HTTP connection pool \"{$name}\" is not registered.");
            }
            return $pool->get();
        }

        $poolManager->register($name, $options);
        return null;
    }

    public function get(string $url, iterable $params = []): Response
    {
        return $this->sendRequest($url, 'GET', $params);
    }

    /**
     * Đăng ký handler nhận body theo từng mảnh thay vì đợi nhận đủ response.
     * Khi bật, Response trả về chỉ còn status và headers - body đã được đẩy hết
     * qua handler.
     */
    public function onChunk(callable $handler): static
    {
        $this->chunkHandler = $handler;
        return $this;
    }

    /**
     * Gửi request và đọc body theo luồng. Dùng cho SSE (LLM streaming) hoặc
     * tải file lớn mà không muốn giữ toàn bộ nội dung trong bộ nhớ.
     *
     * Lưu ý: request dạng stream không tự retry vì các mảnh đã được đẩy đi.
     */
    public function stream(string $url, callable $handler, iterable $params = [], string $method = 'POST'): Response
    {
        return $this->onChunk($handler)->sendRequest($url, strtoupper($method), $params);
    }

    public function post(string $url, iterable $params = []): Response
    {
        return $this->sendRequest($url, 'POST', $params);
    }

    public function put(string $url, iterable $params = []): Response
    {
        return $this->sendRequest($url, 'PUT', $params);
    }

    public function delete(string $url, iterable $params = []): Response
    {
        return $this->sendRequest($url, 'DELETE', $params);
    }

    public function head(string $url, iterable $params = []): Response
    {
        return $this->sendRequest($url, 'HEAD', $params);
    }

    public function patch(string $url, iterable $params = []): Response
    {
        return $this->sendRequest($url, 'PATCH', $params);
    }

    public function timeout(float $timeout): static
    {
        if ($timeout <= 0) {
            throw new \InvalidArgumentException("Timeout must be greater than 0, {$timeout} given.");
        }
        $this->configs['timeout'] = $timeout;
        $this->timeoutOverridden = true;
        return $this;
    }

    public function baseUrl(?string $url = null): static|string
    {
        if (is_null($url)) {
            return $this->baseUrl;
        }
        $trimmed = rtrim(trim($url), '/');
        if (!Url::parse($trimmed)->isAbsolute()) {
            throw InvalidUrlException::for($url);
        }
        $this->baseUrl = $trimmed;
        return $this;
    }

    public function connectTimeout(float $timeout): static
    {
        if ($timeout <= 0) {
            throw new \InvalidArgumentException("Connect timeout must be greater than 0, {$timeout} given.");
        }
        $this->configs['connect_timeout'] = $timeout;
        return $this;
    }

    public function withBasicAuth(string $username, string $password): static
    {
        if ($username === '') {
            throw new \InvalidArgumentException('Basic auth username cannot be empty.');
        }
        $this->username = $username;
        $this->password = $password;
        return $this;
    }

    public function withHeaders(iterable $headers): static
    {
        foreach ($headers as $key => $value) {
            $this->withHeader($key, $value);
        }
        return $this;
    }

    public function withHeader(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function withToken(string $token): static
    {
        return $this->withHeader('Authorization', "Bearer $token");
    }

    public function withApiKey(string $key): static
    {
        return $this->withHeader('X-Api-Key', $key);
    }

    public function asJson(): static
    {
        return $this->withHeader('Content-Type', 'application/json');
    }

    public function asForm(): static
    {
        return $this->withHeader('Content-Type', 'application/x-www-form-urlencoded');
    }

    public function asMultipart(): static
    {
        return $this->withHeader('Content-Type', 'multipart/form-data');
    }

    public function accept(string $type): static
    {
        return $this->withHeader('Accept', $type);
    }

    public function acceptJson(): static
    {
        return $this->accept('application/json');
    }

    public function attach(string $name, string $path, ?string $mimeType = null, ?string $fileName = null, int $offset = 0, int $length = 0): static
    {
        if (isset($this->files[$name])) {
            throw new \LogicException("A file with name \"{$name}\" is already attached.");
        }
        if (!file_exists($path)) {
            throw new \RuntimeException("Attached file not found: \"{$path}\".");
        }
        $this->files[$name] = [
            $path,
            (string) $mimeType,
            (string) $fileName,
            $offset,
            $length
        ];
        return $this->asMultipart();
    }

    public function replaceHeaders(iterable $headers): static
    {
        $this->headers = [];
        return $this->withHeaders($headers);
    }

    public function allowRedirect(
        int $max = 5,
        bool $strict = false,
        bool $referer = false,
        array $protocols = ['http', 'https'],
        bool $trackRedirects = false
    ): static {
        $this->redirectOption = new RedirectOption(
            $max,
            $strict,
            $referer,
            $protocols,
            $trackRedirects
        );
        return $this;
    }

    public function onRedirect(callable $handle): static
    {
        if (is_null($this->redirectOption)) {
            $this->allowRedirect();
        }

        $this->redirectOption->onRedirect = $handle;
        return $this;
    }

    public function retry(int $times, int $sleepMs = 0): static
    {
        if ($times < 1) {
            throw new \InvalidArgumentException("retry() requires at least 1 attempt, {$times} given.");
        }
        if ($sleepMs < 0) {
            throw new \InvalidArgumentException("Retry delay cannot be negative, {$sleepMs}ms given.");
        }
        $this->maxRetries = $times;
        $this->retryDelay = $sleepMs;
        return $this;
    }

    public function withVersion(float $version): static
    {
        if (!in_array($version, [1.0, 1.1, 2.0], true)) {
            throw new \InvalidArgumentException("Unsupported HTTP version {$version}. Allowed: 1.0, 1.1, 2.");
        }
        $this->version = $version;
        return $this;
    }

    public function withParams(array $params): static
    {
        $this->query = array_merge($this->query, $params);
        return $this;
    }

    protected function buildSwooleClient(string $host, int $port, bool $ssl): Client
    {
        return new Client($host, $port, $ssl);
    }

    /**
     * Gắn write_func của Swoole khi có chunk handler để body được đẩy ra ngoài
     * theo từng mảnh thay vì gom hết vào bộ nhớ.
     */
    protected function buildClientConfigs(): array
    {
        if (is_null($this->chunkHandler)) {
            return $this->configs;
        }

        $configs = $this->configs;
        $handler = $this->chunkHandler;

        $configs['write_func'] = static function ($client, string $data) use ($handler): void {
            $handler($data);
        };

        // Stream có thể kéo dài hàng phút (LLM vừa suy nghĩ vừa sinh token).
        // Nếu người gọi không tự chỉ định thì bỏ giới hạn thời gian.
        if (!$this->timeoutOverridden) {
            $configs['timeout'] = -1;
        }

        return $configs;
    }

    protected function sendRequest(string $url, string $method, iterable $params = []): Response
    {
        $params = $params instanceof Arrayable ? $params->toArray() : (new Collection($params))->toArray();
        $savedBaseUrl = $this->baseUrl;
        $savedQuery = $this->query;

        // Request dạng stream không retry được: các mảnh body đã đẩy sang handler,
        // gọi lại lần nữa sẽ khiến consumer nhận dữ liệu trùng.
        if (!is_null($this->chunkHandler)) {
            return $this->followRedirects($url, $method, $params);
        }

        $attempt = 0;
        do {
            $this->baseUrl = $savedBaseUrl;
            $this->query = $savedQuery;
            $attempt++;

            $response = $this->followRedirects($url, $method, $params);

            if (!$response->failed() || $attempt >= $this->maxRetries) {
                return $response;
            }

            if ($this->retryDelay > 0) {
                \Swoole\Coroutine::sleep($this->retryDelay / 1000);
            }
        } while (true);
    }

    private function followRedirects(string $url, string $method, array $params): Response
    {
        if (is_null($this->redirectOption)) {
            return $this->doRequest($url, $method, $params);
        }

        $redirectCount = 0;
        $currentUrl    = $url;
        $currentMethod = $method;
        $currentParams = $params;

        while (true) {
            $response = $this->doRequest($currentUrl, $currentMethod, $currentParams);

            if (!$response->redirect() || $redirectCount >= $this->redirectOption->max) {
                return $response;
            }

            $location = $response->header('location');
            if (empty($location)) {
                return $response;
            }

            // Resolve relative Location to absolute URL
            $locationUrl = Url::parse($location);
            if ($locationUrl->isRelative()) {
                $originUrl = Url::parse($currentUrl)->isRelative()
                    ? Url::parse(rtrim($this->baseUrl, '/'))->appendPath($currentUrl)
                    : Url::parse($currentUrl);
                $locationUrl = $originUrl
                    ->withPath($locationUrl->path())
                    ->withQuery($locationUrl->query());
            }
            $location = $locationUrl->toString();

            // Check protocol is allowed
            if (!in_array($locationUrl->scheme(), $this->redirectOption->protocols)) {
                return $response;
            }

            // Fire onRedirect callback
            if ($this->redirectOption->onRedirect) {
                ($this->redirectOption->onRedirect)($response, $location);
            }

            // Determine method for next request:
            // 303 always → GET; 301/302 non-strict → GET; 307/308 always keep method
            $statusCode = $response->status();
            if ($statusCode === 303 || (in_array($statusCode, [301, 302]) && !$this->redirectOption->strict)) {
                $currentMethod = 'GET';
                $currentParams = [];
            }

            // Append Referer header if configured
            if ($this->redirectOption->referer) {
                $this->headers['Referer'] = $currentUrl;
            }

            // Reset so absolute redirect URL is used as-is in doRequest()
            $this->baseUrl = '';
            $this->query   = [];
            $currentUrl    = $location;
            $redirectCount++;
        }
    }

    private function doRequest(string $url, string $method, array $params): Response
    {
        $trimmedUrl = trim($url);
        $prefix     = (!empty($this->baseUrl) && strpos($trimmedUrl, '?') !== 0)
            ? rtrim($this->baseUrl, '/') . '/'
            : $this->baseUrl;

        $fullUrl = $prefix . ltrim($trimmedUrl, '/');
        $parsed  = Url::parse($fullUrl);

        if ($parsed->host() === null) {
            throw InvalidUrlException::for($fullUrl);
        }

        $scheme = $parsed->scheme();
        if ($scheme !== null && !in_array($scheme, ['http', 'https'], true)) {
            throw InvalidUrlException::unsupportedScheme($scheme, $fullUrl);
        }

        $host = $parsed->host();
        $path = $parsed->path() !== '' ? $parsed->path() : '/';

        // Query viết sẵn trong URL phải được giữ với mọi method. Trước đây chỉ
        // gộp cho GET/HEAD nên POST tới ".../path?a=b" bị mất hẳn phần "?a=b".
        $this->query = array_merge($this->query, $parsed->query()->toArray());

        if (in_array($method, ['GET', 'HEAD'])) {
            $this->query = array_merge($this->query, $params);
        }

        $port        = $parsed->effectivePort() ?? 80;
        $ssl         = $parsed->isSecure();
        $queryString = !empty($this->query) ? '?' . http_build_query($this->query) : '';

        if ($this->version == 2) {
            if (!is_null($this->chunkHandler)) {
                throw new \RuntimeException('Streaming body is not supported on the HTTP/2 client.');
            }
            return $this->doRequestHttp2($host, $port, $ssl, $method, $path . $queryString, $params);
        }

        $swooleClient = $this->buildSwooleClient($host, $port, $ssl);

        $headers = $this->headers;
        if (!empty($this->files)) {
            foreach ($this->files as $name => $file) {
                $swooleClient->addFile(
                    $file[0],
                    $name,
                    $file[1],
                    $file[2],
                    $file[3],
                    $file[4],
                );
            }
            unset($headers['Content-Type']);
        }

        $contentType = $headers['Content-Type'] ?? '';
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            if (strpos($contentType, 'application/json') !== false) {
                $swooleClient->setData(json_encode($params));
            } elseif (str_contains($contentType, 'multipart/form-data')) {
                [$body, $boundary] = $this->buildMultipartBody($params);
                $headers['Content-Type'] = "multipart/form-data; boundary={$boundary}";
                $headers['Content-Length'] = (string) strlen($body);
                $swooleClient->setData($body);
            } else {
                $swooleClient->setData($params);
            }
        }

        if (!empty($headers)) {
            $swooleClient->setHeaders($headers);
        }

        $swooleClient->set($this->buildClientConfigs());
        $swooleClient->setMethod($method);

        if (!empty($this->username)) {
            $swooleClient->setBasicAuth($this->username, $this->password);
        }

        $swooleClient->execute($path . $queryString);
        $swooleClient->close();

        return new HttpClientResponse($swooleClient);
    }

    private function doRequestHttp2(string $host, int $port, bool $ssl, string $method, string $path, array $params): Response
    {
        $client = new Http2Client($host, $port, $ssl);
        $client->set($this->configs);

        if (!$client->connect()) {
            throw new \RuntimeException("Failed to connect to {$host}:{$port} via HTTP/2.");
        }

        $request         = new \Swoole\Http2\Request();
        $request->method = $method;
        $request->path   = $path;

        $headers = $this->headers;
        if (!empty($this->username)) {
            $headers['Authorization'] = 'Basic ' . base64_encode("{$this->username}:{$this->password}");
        }

        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            if (!empty($this->files)) {
                [$body, $boundary] = $this->buildMultipartBody($params);
                $headers['Content-Type']   = "multipart/form-data; boundary={$boundary}";
                $headers['Content-Length'] = (string) strlen($body);
                $request->data             = $body;
            } else {
                $contentType   = $headers['Content-Type'] ?? '';
                $request->data = str_contains($contentType, 'application/json')
                    ? json_encode($params)
                    : http_build_query($params);
            }
        }

        $request->headers = $headers;

        $streamId = $client->send($request);
        if ($streamId === false) {
            $client->close();
            throw new \RuntimeException("Failed to send HTTP/2 request to {$host}:{$port}.");
        }

        $response = $client->recv();
        $client->close();

        if ($response === false) {
            throw new \RuntimeException("HTTP/2 response timed out or failed for {$host}:{$port}.");
        }

        return new HttpClientResponse($response);
    }

    /**
     * Tự build multipart/form-data body cho HTTP/2 (vì Swoole Http2Client không có addFile()).
     * Trả về [body_string, boundary].
     */
    private function buildMultipartBody(array $params): array
    {
        $boundary = '----SwooleBoundary' . bin2hex(random_bytes(8));
        $body     = '';

        // Các field text thông thường
        foreach ($params as $name => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= "{$value}\r\n";
        }

        // Các file đính kèm
        foreach ($this->files as $name => [$path, $mimeType, $fileName, $offset, $length]) {
            $fileName = $fileName !== '' ? $fileName : basename($path);
            $mimeType = $mimeType !== '' ? $mimeType : 'application/octet-stream';

            $fp      = fopen($path, 'rb');
            if ($fp === false) {
                throw new \RuntimeException("Cannot open file for upload: \"{$path}\".");
            }
            if ($offset > 0) {
                fseek($fp, $offset);
            }
            $content = $length > 0 ? fread($fp, $length) : stream_get_contents($fp);
            fclose($fp);

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$fileName}\"\r\n";
            $body .= "Content-Type: {$mimeType}\r\n\r\n";
            $body .= $content . "\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        return [$body, $boundary];
    }
}