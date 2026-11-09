<?php

namespace Ryra\Core\Grpc\Attributes;

use Attribute;

/**
 * Đánh dấu một class là protobuf message.
 *
 * - name: tên message trong file .proto (mặc định là tên class).
 * - package: package proto. Bỏ trống thì message sẽ thuộc package của service đầu tiên dùng nó.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Message
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $package = null,
        public readonly ?string $comment = null,
    ) {}
}
