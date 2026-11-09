<?php

namespace Ryra\Core\Grpc\Health;

use Ryra\Core\Grpc\Attributes\Enum;

#[Enum(package: 'grpc.health.v1', prefix: false)]
enum ServingStatus: int
{
    case UNKNOWN = 0;
    case SERVING = 1;
    case NOT_SERVING = 2;
    case SERVICE_UNKNOWN = 3;
}
