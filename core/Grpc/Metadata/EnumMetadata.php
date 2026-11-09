<?php

namespace Ryra\Core\Grpc\Metadata;

final class EnumMetadata
{
    /**
     * @param array<string, int> $values Tên value trong proto => số.
     */
    public function __construct(
        public readonly string $class,
        public readonly string $name,
        public readonly ?string $package,
        public readonly array $values,
        public readonly ?string $comment = null,
    ) {}
}
