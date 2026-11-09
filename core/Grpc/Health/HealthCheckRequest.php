<?php

namespace Ryra\Core\Grpc\Health;

use Ryra\Core\Grpc\Attributes\Field;
use Ryra\Core\Grpc\Attributes\Message;

#[Message(package: 'grpc.health.v1')]
final class HealthCheckRequest
{
    public function __construct(#[Field(1)] public string $service = '') {}
}
