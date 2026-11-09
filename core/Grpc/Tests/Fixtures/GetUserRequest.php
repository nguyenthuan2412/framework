<?php

namespace Ryra\Core\Grpc\Tests\Fixtures;

final class GetUserRequest
{
    public function __construct(public readonly int $id = 0) {}
}
