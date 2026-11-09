<?php

namespace Ryra\Core\Grpc\Attributes;

use Attribute;
use Ryra\Core\Grpc\Protobuf\Type;

/**
 * Tuỳ chỉnh một field của message.
 *
 * - number: số thứ tự field (tag). Bỏ trống thì tự đánh số tiếp theo theo thứ tự khai báo.
 *   Khuyến nghị luôn đặt số cố định để giữ tương thích khi thêm/bớt field.
 * - type: kiểu proto (Type::Int32, Type::Sint64, ...) hoặc class message/enum.
 *   Với property kiểu array, đây là kiểu của từng phần tử (repeated) hoặc kiểu value (map).
 * - key: đặt khi field là map, là kiểu của key (Type::String, Type::Int64, ...).
 * - name: tên field trong .proto (mặc định là snake_case của tên property).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Field
{
    public function __construct(
        public readonly ?int $number = null,
        public readonly Type|string|null $type = null,
        public readonly Type|null $key = null,
        public readonly ?string $name = null,
        public readonly bool $deprecated = false,
        public readonly ?string $comment = null,
    ) {}
}
