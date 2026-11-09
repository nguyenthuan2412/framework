<?php

namespace Ryra\Core\Grpc\Tests\Fixtures;

use Ryra\Core\Grpc\Attributes\Field;
use Ryra\Core\Grpc\Attributes\Message;

#[Message(package: 'common.v1')]
final class Address
{
    public function __construct(
        #[Field(1)] public string $street = '',
        #[Field(2)] public string $city = '',
    ) {}
}
