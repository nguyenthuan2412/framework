<?php

namespace Ryra\Core\Grpc\Attributes;

use Attribute;

/**
 * Tuỳ chỉnh enum (int-backed) khi sinh ra .proto.
 *
 * - prefix: thêm tiền tố tên enum vào tên value (USER_STATUS_ACTIVE) theo style guide của protobuf.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Enum
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $package = null,
        public readonly bool $prefix = true,
        public readonly ?string $comment = null,
    ) {}
}
