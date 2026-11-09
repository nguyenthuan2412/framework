<?php

namespace Ryra\Core\Grpc\Metadata;

use Ryra\Core\Grpc\Protobuf\Type;

final class FieldMetadata
{
    /**
     * @param string|null $class Class message / enum / DateTime khi type là Message, Enum hoặc Timestamp.
     * @param Type|null $keyType Khác null nghĩa là field là map<keyType, type>.
     */
    public function __construct(
        public readonly string $property,
        public readonly string $protoName,
        public readonly int $number,
        public readonly Type $type,
        public readonly ?string $class,
        public readonly bool $repeated,
        public readonly ?Type $keyType,
        public readonly bool $nullable,
        public readonly bool $hasDefault,
        public readonly mixed $default,
        public readonly string $declaringClass,
        public readonly bool $deprecated = false,
        public readonly ?string $comment = null,
    ) {}

    public function isMap(): bool
    {
        return $this->keyType !== null;
    }

    /**
     * proto3 `optional`: scalar nullable có presence.
     */
    public function isOptional(): bool
    {
        return $this->nullable && !$this->repeated && !$this->isMap()
            && $this->type !== Type::Message && $this->type !== Type::Timestamp;
    }
}
