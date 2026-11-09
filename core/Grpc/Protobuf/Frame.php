<?php

namespace Ryra\Core\Grpc\Protobuf;

use Ryra\Core\Grpc\Exception\DecodeException;

/**
 * Đóng gói message theo gRPC over HTTP/2:
 * 1 byte compressed flag + 4 byte độ dài (big-endian) + payload.
 */
final class Frame
{
    public static function pack(string $payload, bool $gzip = false): string
    {
        if ($gzip) {
            $payload = \gzencode($payload);
        }
        return \pack('CN', $gzip ? 1 : 0, \strlen($payload)) . $payload;
    }

    /**
     * Tách tất cả message trong body.
     *
     * @return string[]
     */
    public static function unpackAll(string $body, ?string $encoding = null): array
    {
        $messages = [];
        $offset = 0;
        $length = \strlen($body);

        while ($offset < $length) {
            if ($length - $offset < 5) {
                throw new DecodeException('Truncated gRPC frame header');
            }
            ['flag' => $flag, 'len' => $size] = \unpack('Cflag/Nlen', $body, $offset);
            $offset += 5;
            if ($length - $offset < $size) {
                throw new DecodeException('Truncated gRPC frame payload');
            }
            $payload = \substr($body, $offset, $size);
            $offset += $size;

            if ($flag === 1) {
                $payload = match ($encoding) {
                    'gzip' => \gzdecode($payload),
                    'deflate' => \gzuncompress($payload),
                    default => throw new DecodeException("Unsupported grpc-encoding [$encoding]"),
                };
                if ($payload === false) {
                    throw new DecodeException('Cannot decompress gRPC message');
                }
            }
            $messages[] = $payload;
        }

        return $messages;
    }
}
