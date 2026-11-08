<?php

namespace Ryra\Core\Datastar\Stream;

/**
 * Nơi generator ghi dữ liệu SSE ra. Tách interface để test format event mà
 * không cần server Swoole thật.
 */
interface StreamWriter
{
    /**
     * Ghi một chunk, trả về false khi client đã ngắt kết nối.
     */
    public function write(string $chunk): bool;

    /**
     * Kết nối còn ghi được hay không.
     */
    public function isWritable(): bool;

    /**
     * Đóng luồng, gọi nhiều lần cũng không sao.
     */
    public function close(): void;
}
