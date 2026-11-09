<?php

namespace Ryra\Core\Grpc\Attributes;

use Attribute;

/**
 * Tuỳ chỉnh một rpc.
 *
 * - name: tên rpc trong .proto (mặc định là tên method viết hoa chữ cái đầu).
 * - request / response: class message, bắt buộc khi tham số/kiểu trả về là iterable (stream).
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Rpc
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $request = null,
        public readonly ?string $response = null,
        public readonly ?string $comment = null,
    ) {}
}
