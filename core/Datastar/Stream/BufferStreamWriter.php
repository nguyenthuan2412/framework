<?php

namespace Ryra\Core\Datastar\Stream;

/**
 * Gom toàn bộ event vào bộ nhớ. Dùng cho test và cho các trường hợp cần lấy
 * chuỗi SSE ra để gửi qua kênh khác (queue, websocket, log...).
 */
class BufferStreamWriter implements StreamWriter
{
    private string $buffer = '';

    private bool $closed = false;

    public function write(string $chunk): bool
    {
        if ($this->closed) {
            return false;
        }

        $this->buffer .= $chunk;

        return true;
    }

    public function isWritable(): bool
    {
        return !$this->closed;
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function getContent(): string
    {
        return $this->buffer;
    }

    public function flush(): string
    {
        $buffer = $this->buffer;
        $this->buffer = '';

        return $buffer;
    }
}
