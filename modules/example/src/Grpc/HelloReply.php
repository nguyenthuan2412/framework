<?php

namespace Ryra\Modules\Example\Grpc;

use Ryra\Core\Grpc\Attributes\Field;
use Ryra\Core\Grpc\Attributes\Message;

#[Message]
final class HelloReply
{
    public function __construct(
        #[Field(1)] public string $message = '',
        #[Field(2)] public ?\DateTimeImmutable $repliedAt = null,
    ) {}
}
