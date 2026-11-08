<?php

namespace Ryra\Core\AI\Http;

use Generator;
use Ryra\Core\AI\Exceptions\ProviderException;
use Ryra\Core\App;
use Ryra\Core\Contracts\HttpClient\Http;
use Ryra\Core\HttpClient\Http as HttpClient;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Throwable;

/**
 * Lớp truyền tải HTTP dùng chung cho mọi driver AI.
 *
 * Bọc quanh core/HttpClient để thống nhất phần xử lý lỗi, retry và đọc SSE,
 * tránh việc mỗi driver tự lặp lại những đoạn này.
 */
class Transport
{
    /**
     * Giữ tối đa 8KB đầu của body khi stream để dựng thông báo lỗi,
     * không gom cả stream vào bộ nhớ.
     */
    protected const ERROR_BODY_LIMIT = 8192;

    public function __construct(
        protected string $provider,
        protected float $timeout = 120,
        protected float $connectTimeout = 10,
        protected int $maxRetries = 2,
        protected int $retryDelayMs = 500,
        /** Sức chứa hàng đợi bắc cầu giữa coroutine đọc socket và caller. */
        protected int $channelSize = 64,
        /**
         * Hàm tạo HTTP client. Mặc định lấy từ container; cho phép truyền vào
         * để test không cần dựng cả ứng dụng.
         *
         * @var (callable():Http)|null
         */
        protected mixed $clientFactory = null,
    ) {}

    /**
     * @param  array<string, string> $headers
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function post(string $url, array $headers, array $body): array
    {
        return $this->request('POST', $url, $headers, $body);
    }

    /**
     * @param  array<string, string> $headers
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function put(string $url, array $headers, array $body): array
    {
        return $this->request('PUT', $url, $headers, $body);
    }

    /**
     * @param  array<string, string> $headers
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function delete(string $url, array $headers, array $body = []): array
    {
        return $this->request('DELETE', $url, $headers, $body);
    }

    /**
     * @param  array<string, string> $headers
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function get(string $url, array $headers): array
    {
        return $this->request('GET', $url, $headers, []);
    }

    /**
     * @param  array<string, string> $headers
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function request(string $method, string $url, array $headers, array $body = []): array
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return $this->send($method, $url, $headers, $body);
            } catch (ProviderException $e) {
                if (!$e->isRetryable() || $attempt > $this->maxRetries) {
                    throw $e;
                }

                // Nhà cung cấp nói rõ phải chờ bao lâu thì nghe theo, backoff
                // của mình chỉ là mức sàn.
                $this->sleep(max($this->backoff($attempt), $e->retryAfter() ?? 0));
            }
        }
    }

    /**
     * @param  array<string, string> $headers
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function send(string $method, string $url, array $headers, array $body): array
    {
        // Tự quản vòng lặp thử lại nên tắt retry của HttpClient để không nhân đôi.
        $client = $this->client($headers)->timeout($this->timeout)->retry(1);

        $response = match (strtoupper($method)) {
            'GET' => $client->get($url, $body),
            'PUT' => $client->put($url, $body),
            'PATCH' => $client->patch($url, $body),
            'DELETE' => $client->delete($url, $body),
            default => $client->post($url, $body),
        };

        $decoded = $this->decode($response->body());

        if ($response->failed()) {
            throw ProviderException::fromResponse($this->provider, $response->status(), $decoded, $response->body());
        }

        return $decoded;
    }

    /**
     * Mở kết nối stream và nhả từng SSE event ra ngoài ngay khi nhận được.
     *
     * HttpClient đẩy dữ liệu qua callback nên không thể yield trực tiếp từ trong đó.
     * Trong coroutine ta chạy request ở một coroutine riêng và bắc cầu qua Channel,
     * nhờ vậy caller nhận token theo thời gian thực thay vì đợi hết response.
     *
     * @param  array<string, string> $headers
     * @param  array<string, mixed>  $body
     * @return Generator<int, SseEvent>
     */
    public function stream(string $url, array $headers, array $body): Generator
    {
        if (!$this->inCoroutine()) {
            yield from $this->streamBuffered($url, $headers, $body);
            return;
        }

        $channel = new Channel($this->channelSize);

        Coroutine::create(function () use ($channel, $url, $headers, $body): void {
            try {
                $this->pump($url, $headers, $body, static function (SseEvent $event) use ($channel): void {
                    $channel->push($event);
                });
            } catch (Throwable $e) {
                $channel->push($e);
            } finally {
                // Terminator: báo cho consumer biết producer đã kết thúc.
                $channel->push(null);
            }
        });

        while (true) {
            $item = $channel->pop();

            if ($item === null || $item === false) {
                break;
            }

            if ($item instanceof Throwable) {
                throw $item;
            }

            yield $item;
        }
    }

    /**
     * Chạy request stream và gọi $onEvent cho mỗi SSE event nhận được.
     *
     * @param array<string, string>   $headers
     * @param array<string, mixed>    $body
     * @param callable(SseEvent):void $onEvent
     */
    protected function pump(string $url, array $headers, array $body, callable $onEvent): void
    {
        $parser = new SseParser();
        $errorBody = '';

        // Khi đọc theo chunk, Swoole trả về byte thô và không tự giải nén.
        // Yêu cầu server gửi nguyên bản để parse SSE và thông báo lỗi không bị hỏng.
        $headers['Accept-Encoding'] = 'identity';

        $response = $this->client($headers)->stream(
            $url,
            static function (string $chunk) use ($parser, $onEvent, &$errorBody): void {
                if (strlen($errorBody) < static::ERROR_BODY_LIMIT) {
                    $errorBody .= $chunk;
                }

                foreach ($parser->push($chunk) as $event) {
                    $onEvent($event);
                }
            },
            $body,
        );

        if ($response->failed()) {
            throw ProviderException::fromResponse(
                $this->provider,
                $response->status(),
                $this->decode($errorBody),
                $errorBody,
            );
        }

        if (($tail = $parser->flush()) !== null) {
            $onEvent($tail);
        }
    }

    /**
     * Fallback khi chạy ngoài coroutine (CLI, unit test): gom hết rồi nhả một lượt.
     *
     * @param  array<string, string> $headers
     * @param  array<string, mixed>  $body
     * @return Generator<int, SseEvent>
     */
    protected function streamBuffered(string $url, array $headers, array $body): Generator
    {
        $events = [];

        $this->pump($url, $headers, $body, static function (SseEvent $event) use (&$events): void {
            $events[] = $event;
        });

        yield from $events;
    }

    /**
     * @param array<string, string> $headers
     */
    protected function client(array $headers): Http
    {
        return $this->resolveClient()
            ->asJson()
            ->acceptJson()
            ->withHeaders($headers)
            ->connectTimeout($this->connectTimeout);
    }

    /**
     * Ưu tiên lấy từ container để tôn trọng cấu hình của ứng dụng; nếu container
     * chưa khởi động (script CLI, test) thì tự dựng client mặc định.
     */
    protected function resolveClient(): Http
    {
        if ($this->clientFactory !== null) {
            return ($this->clientFactory)();
        }

        try {
            return App::service('http.client');
        } catch (Throwable) {
            return new HttpClient();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(string $raw): array
    {
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Backoff luỹ thừa theo số lần đã thử, tính bằng giây.
     */
    protected function backoff(int $attempt): float
    {
        return ($this->retryDelayMs * (2 ** ($attempt - 1))) / 1000;
    }

    /**
     * Trong coroutine phải dùng Coroutine::sleep để không chặn worker.
     */
    protected function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        if ($this->inCoroutine()) {
            Coroutine::sleep($seconds);
            return;
        }

        usleep((int) ($seconds * 1_000_000));
    }

    /**
     * Đang chạy bên trong coroutine của Swoole hay không.
     * Bên ngoài môi trường Swoole (unit test, script CLI) sẽ trả về false.
     */
    protected function inCoroutine(): bool
    {
        if (!class_exists(Coroutine::class)) {
            return false;
        }

        try {
            return Coroutine::getCid() > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
