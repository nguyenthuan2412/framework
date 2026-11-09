<?php

namespace Ryra\Core\Grpc\Protobuf;

use Ryra\Core\Grpc\Exception\DecodeException;

/**
 * Hàm runtime đọc/ghi wire format protobuf, dùng bởi encoder/decoder do {@see WireCompiler} sinh ra.
 *
 * https://protobuf.dev/programming-guides/encoding/
 */
final class Wire
{
    public const VARINT = 0;
    public const I64 = 1;
    public const LEN = 2;
    public const I32 = 5;

    /**
     * Varint không dấu 64-bit (số âm được hiểu là bù 2, luôn chiếm 10 byte).
     */
    public static function varint(int $n): string
    {
        $s = '';
        if ($n < 0) {
            $s = \chr(($n & 0x7F) | 0x80);
            $n = ($n >> 7) & 0x01FFFFFFFFFFFFFF;
        }
        while ($n > 0x7F) {
            $s .= \chr(($n & 0x7F) | 0x80);
            $n >>= 7;
        }
        return $s . \chr($n);
    }

    public static function tag(int $number, int $wireType): string
    {
        return self::varint(($number << 3) | $wireType);
    }

    public static function readVarint(string $bytes, int &$offset): int
    {
        $result = 0;
        for ($shift = 0; $shift < 70; $shift += 7) {
            if (!isset($bytes[$offset])) {
                throw self::truncated();
            }
            $byte = \ord($bytes[$offset++]);
            $result |= ($byte & 0x7F) << $shift;
            if ($byte < 0x80) {
                return $result;
            }
        }
        throw new DecodeException('Malformed varint');
    }

    /**
     * Bỏ qua field không biết (forward compatibility).
     */
    public static function skip(string $bytes, int &$offset, int $tag, int $length): void
    {
        switch ($tag & 7) {
            case self::VARINT:
                self::readVarint($bytes, $offset);
                return;
            case self::I64:
                $offset += 8;
                break;
            case self::LEN:
                $offset += self::readVarint($bytes, $offset);
                break;
            case self::I32:
                $offset += 4;
                break;
            default:
                throw new DecodeException('Unsupported wire type ' . ($tag & 7) . ' for field ' . ($tag >> 3));
        }
        if ($offset > $length) {
            throw self::truncated();
        }
    }

    /**
     * Body của google.protobuf.Timestamp.
     */
    public static function timestamp(\DateTimeInterface|int|string $value): string
    {
        if (!$value instanceof \DateTimeInterface) {
            $value = new \DateTimeImmutable(\is_int($value) ? "@$value" : $value);
        }
        $seconds = $value->getTimestamp();
        $nanos = (\PHP_VERSION_ID >= 80400 ? $value->getMicrosecond() : (int) $value->format('u')) * 1000;
        return ($seconds !== 0 ? "\x08" . self::varint($seconds) : '')
            . ($nanos !== 0 ? "\x10" . self::varint($nanos) : '');
    }

    /**
     * @return array{0: int, 1: int} [seconds, nanos]
     */
    public static function readTimestamp(string $bytes): array
    {
        $seconds = $nanos = 0;
        $offset = 0;
        $length = \strlen($bytes);
        while ($offset < $length) {
            $tag = self::readVarint($bytes, $offset);
            match ($tag) {
                0x08 => $seconds = self::readVarint($bytes, $offset),
                0x10 => $nanos = self::readVarint($bytes, $offset) << 32 >> 32,
                default => self::skip($bytes, $offset, $tag, $length),
            };
        }
        return [$seconds, $nanos];
    }

    public static function truncated(): DecodeException
    {
        return new DecodeException('Truncated protobuf message');
    }
}
