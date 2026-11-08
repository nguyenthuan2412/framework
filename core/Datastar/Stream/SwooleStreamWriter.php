<?php

namespace Ryra\Core\Datastar\Stream;

use Swoole\Http\Response as SwooleResponse;

/**
 * Ghi thẳng vào response của Swoole bằng chunked transfer.
 *
 * Swoole tự tắt nén khi dùng write() nên event tới client ngay lập tức, không
 * bị gom vào buffer gzip.
 */
class SwooleStreamWriter implements StreamWriter
{
    private bool $closed = false;

    public function __construct(private SwooleResponse $response) {}

    public function write(string $chunk): bool
    {
        if (!$this->isWritable()) {
            return false;
        }

        // write() trả về false khi client đóng tab hoặc huỷ request
        if ($this->response->write($chunk) === false) {
            $this->closed = true;
            return false;
        }

        return true;
    }

    public function isWritable(): bool
    {
        if ($this->closed) {
            return false;
        }

        // isWritable() có từ Swoole 4.6, kiểm tra để không vỡ ở bản cũ hơn
        if (\method_exists($this->response, 'isWritable') && !$this->response->isWritable()) {
            $this->closed = true;
            return false;
        }

        return true;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->response->end();
    }
}
