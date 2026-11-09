<?php

namespace Ryra\Core\Grpc\Metadata;

final class MessageMetadata
{
    /** @var array<int, FieldMetadata> */
    public readonly array $fieldsByNumber;

    /**
     * @param array<string, FieldMetadata> $fields Theo tên property, đúng thứ tự khai báo.
     * @param string|null $wellKnown Đường dẫn import nếu là well-known type (vd google/protobuf/empty.proto).
     */
    public function __construct(
        public readonly string $class,
        public readonly string $name,
        public readonly ?string $package,
        public readonly array $fields,
        public readonly ?string $comment = null,
        public readonly ?string $wellKnown = null,
    ) {
        $byNumber = [];
        foreach ($fields as $field) {
            $byNumber[$field->number] = $field;
        }
        $this->fieldsByNumber = $byNumber;
    }
}
