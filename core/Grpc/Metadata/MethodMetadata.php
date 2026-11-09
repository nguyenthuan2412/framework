<?php

namespace Ryra\Core\Grpc\Metadata;

final class MethodMetadata
{
    /**
     * @param int|null $requestPosition Vị trí tham số nhận request trong method PHP.
     * @param int|null $contextPosition Vị trí tham số ServerContext (nếu có).
     */
    public function __construct(
        public readonly string $phpMethod,
        public readonly string $name,
        public readonly string $requestClass,
        public readonly string $responseClass,
        public readonly bool $clientStreaming,
        public readonly bool $serverStreaming,
        public readonly ?int $requestPosition,
        public readonly ?int $contextPosition,
        public readonly int $parameterCount,
        public readonly bool $returnsVoid = false,
        public readonly ?string $comment = null,
    ) {}
}
