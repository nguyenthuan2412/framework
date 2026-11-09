<?php

namespace Ryra\Core\Grpc\Health;

use Ryra\Core\Grpc\Attributes\Field;
use Ryra\Core\Grpc\Attributes\Message;

#[Message(package: 'grpc.health.v1')]
final class HealthCheckResponse
{
    public function __construct(#[Field(1)] public ServingStatus $status = ServingStatus::UNKNOWN) {}
}
