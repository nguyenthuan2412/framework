<?php

namespace Ryra\Modules\Example\Grpc;

use Ryra\Core\Grpc\Attributes\Field;
use Ryra\Core\Grpc\Attributes\Message;

#[Message]
final class HelloRequest
{
    public function __construct(
        #[Field(1)] public string $name = '',
        #[Field(2)] public int $times = 1,
    ) {}
}
