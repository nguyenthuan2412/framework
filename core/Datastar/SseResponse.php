<?php

namespace Ryra\Core\Datastar;

use Ryra\Core\Datastar\Stream\BufferStreamWriter;
use Ryra\Core\Datastar\Stream\SwooleStreamWriter;
use Ryra\Core\Http\Response;
use Ryra\Core\Log\Log;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Timer;

/**
 * Response mở một luồng SSE tới client Datastar.
 *
 * Khác `StreamedResponse` (chỉ nhận generator yield ra chuỗi), response này đưa
 * hẳn `ServerSentEventGenerator` cho callback nên có thể patch element/signal ở
 * bất kỳ đâu trong luồng xử lý, kể cả trong vòng lặp chạy dài.
 */
class SseResponse extends Response
{
    private bool $streamed = false;

    /**
     * @param \Closure(ServerSentEventGenerator, SseResponse): void $handler
     * @param array{retry_duration?: int, use_view_transition?: bool, keep_alive?: int} $options
     * @param \Closure|null $viewRenderer Hàm render view cho `patchView()`
     */
    public function __construct(
        private \Closure $handler,
        int $status = 200,
        array $headers = [],
        private array $options = [],
        private ?\Closure $viewRenderer = null,
    ) {
        parent::__construct(null, $status, \array_merge($this->defaultHeaders(), $headers));
    }

    /**
     * Header bắt buộc theo SDK spec.
     *
     * Manager truyền thêm header từ `config/datastar.php` khi khởi tạo, nên ở
     * đây chỉ giữ mức tối thiểu để response dùng được cả khi tạo trực tiếp.
     *
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',

            // Tắt buffer của nginx để event tới client ngay
            'X-Accel-Buffering' => 'no',
        ];
    }

    public function setContent(?string $content): static
    {
        if ($content !== null && $content !== '') {
            throw new \LogicException('Không thể set content cho SseResponse, hãy dùng ServerSentEventGenerator.');
        }

        $this->content = '';

        return $this;
    }

    public function getContent(): string|false
    {
        return false;
    }

    public function sendContent(SwooleResponse $swooleResponse): static
    {
        if ($this->streamed) {
            return $this;
        }

        $this->streamed = true;

        $writer = new SwooleStreamWriter($swooleResponse);
        $sse = new ServerSentEventGenerator($writer, $this->options, $this->viewRenderer);
        $keepAliveTimer = $this->startKeepAlive($sse);

        try {
            ($this->handler)($sse, $this);
        } catch (\Throwable $th) {
            // Header đã gửi đi rồi nên không thể trả lỗi 500, chỉ ghi log lại
            Log::error('Datastar SSE stream error: ' . $th->getMessage(), [
                'exception' => $th,
            ]);
        } finally {
            if ($keepAliveTimer !== null) {
                Timer::clear($keepAliveTimer);
            }

            $writer->close();
        }

        return $this;
    }

    /**
     * Chạy handler và trả về chuỗi SSE thay vì gửi đi.
     *
     * Dùng trong test controller: khẳng định được server trả đúng những event
     * mong đợi mà không cần dựng server Swoole.
     */
    public function capture(): string
    {
        $writer = new BufferStreamWriter();

        ($this->handler)(new ServerSentEventGenerator($writer, $this->options, $this->viewRenderer), $this);

        return $writer->getContent();
    }

    /**
     * Gửi comment định kỳ để proxy không cắt kết nối idle.
     */
    private function startKeepAlive(ServerSentEventGenerator $sse): ?int
    {
        $seconds = (int) ($this->options['keep_alive'] ?? 0);

        if ($seconds <= 0) {
            return null;
        }

        return Timer::tick($seconds * 1000, static function (int $timerId) use ($sse) {
            if (!$sse->isConnected()) {
                Timer::clear($timerId);
                return;
            }

            $sse->comment('keep-alive');
        });
    }
}
