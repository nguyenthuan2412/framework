<?php

namespace Ryra\Core\Grpc\Attributes;

use Attribute;

/**
 * Đánh dấu class (hoặc interface) là một gRPC service.
 *
 * Mọi method public khai báo trong class đều trở thành rpc,
 * dùng #[Rpc] để tuỳ chỉnh hoặc #[Ignore] để bỏ qua.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Service
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly string $package = 'app',
        public readonly ?string $comment = null,
    ) {}
}
