<?php

namespace Ryra\Core\Grpc\Tests\Fixtures;

enum UserStatus: int
{
    case Active = 1;
    case Banned = 2;
}
