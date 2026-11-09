<?php

namespace Ryra\Core\Grpc\Protobuf;

/**
 * Kiểu dữ liệu protobuf.
 *
 * Scalar dùng trực tiếp, Message/Enum/Timestamp được suy ra từ kiểu PHP của property.
 */
enum Type: string
{
    case Double = 'double';
    case Float = 'float';
    case Int32 = 'int32';
    case Int64 = 'int64';
    case Uint32 = 'uint32';
    case Uint64 = 'uint64';
    case Sint32 = 'sint32';
    case Sint64 = 'sint64';
    case Fixed32 = 'fixed32';
    case Fixed64 = 'fixed64';
    case Sfixed32 = 'sfixed32';
    case Sfixed64 = 'sfixed64';
    case Bool = 'bool';
    case String = 'string';
    case Bytes = 'bytes';
    case Message = 'message';
    case Enum = 'enum';
    case Timestamp = 'google.protobuf.Timestamp';

    /**
     * Kiểu được phép làm key của map.
     */
    public function isValidMapKey(): bool
    {
        return !\in_array($this, [self::Double, self::Float, self::Bytes, self::Message, self::Enum, self::Timestamp], true);
    }

    public function isScalar(): bool
    {
        return !\in_array($this, [self::Message, self::Enum, self::Timestamp], true);
    }

    public function phpType(): string
    {
        return match ($this) {
            self::Double, self::Float => 'float',
            self::Bool => 'bool',
            self::String, self::Bytes => 'string',
            self::Message, self::Enum, self::Timestamp => 'object',
            default => 'int',
        };
    }
}
