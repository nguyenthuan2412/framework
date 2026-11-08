<?php

namespace Ryra\Core\AI\Tests\Fixtures;

use Generator;
use Ryra\Core\AI\Http\SseEvent;
use Ryra\Core\AI\Http\Transport;

/**
 * Transport giả: trả về SSE event dựng sẵn thay vì gọi ra ngoài mạng.
 */
class FakeTransport extends Transport
{
    /**
     * @var array<int, array{url: string, headers: array, body: array}>
     */
    public array $requests = [];

    /**
     * @param array<int, SseEvent>      $events
     * @param array<string, mixed>|null $response
     */
    public function __construct(
        protected array $events = [],
        protected ?array $response = null,
    ) {
        parent::__construct('fake');
    }

    /**
     * Tách chuỗi SSE thô thành từng event, mô phỏng đúng dữ liệu server gửi về.
     *
     * @return array<int, SseEvent>
     */
    public static function eventsFrom(string $raw): array
    {
        $events = [];
        $parser = new \Ryra\Core\AI\Http\SseParser();

        foreach ($parser->push($raw) as $event) {
            $events[] = $event;
        }

        if (($tail = $parser->flush()) !== null) {
            $events[] = $tail;
        }

        return $events;
    }

    public function post(string $url, array $headers, array $body): array
    {
        $this->requests[] = ['url' => $url, 'headers' => $headers, 'body' => $body];

        return $this->response ?? [];
    }

    public function stream(string $url, array $headers, array $body): Generator
    {
        $this->requests[] = ['url' => $url, 'headers' => $headers, 'body' => $body];

        yield from $this->events;
    }
}
