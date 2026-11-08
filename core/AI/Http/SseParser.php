<?php

namespace Ryra\Core\AI\Http;

use Generator;

/**
 * Bộ gom Server-Sent Events. Nhận từng mảnh byte rời rạc từ socket và
 * chỉ nhả ra event khi đã nhận đủ (kết thúc bằng dòng trống).
 */
class SseParser
{
    protected string $buffer = '';

    /**
     * @return Generator<int, SseEvent>
     */
    public function push(string $chunk): Generator
    {
        $this->buffer .= $chunk;

        // Chuẩn hoá CRLF để tách block bằng "\n\n" trong mọi trường hợp.
        $this->buffer = str_replace("\r\n", "\n", $this->buffer);

        while (($position = strpos($this->buffer, "\n\n")) !== false) {
            $block = substr($this->buffer, 0, $position);
            $this->buffer = substr($this->buffer, $position + 2);

            $event = $this->parseBlock($block);

            if ($event !== null) {
                yield $event;
            }
        }
    }

    /**
     * Phần còn sót lại chưa kết thúc bằng dòng trống (server đóng kết nối sớm).
     */
    public function flush(): ?SseEvent
    {
        $block = trim($this->buffer);
        $this->buffer = '';

        return $block === '' ? null : $this->parseBlock($block);
    }

    protected function parseBlock(string $block): ?SseEvent
    {
        $name = null;
        $data = [];

        foreach (explode("\n", $block) as $line) {
            if ($line === '' || str_starts_with($line, ':')) {
                continue;
            }

            $separator = strpos($line, ':');

            if ($separator === false) {
                continue;
            }

            $field = substr($line, 0, $separator);
            $value = ltrim(substr($line, $separator + 1), ' ');

            match ($field) {
                'event' => $name = $value,
                'data' => $data[] = $value,
                default => null,
            };
        }

        return $data === [] ? null : new SseEvent($name, implode("\n", $data));
    }
}
