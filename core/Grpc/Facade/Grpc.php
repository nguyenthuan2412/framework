<?php

namespace Ryra\Core\Grpc\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static \Ryra\Core\Grpc\Client\GrpcClient connection(?string $name = null)
 * @method static \Ryra\Core\Grpc\Client\GrpcClient to(string $address, array $options = [])
 * @method static \Ryra\Core\Grpc\Client\ServiceStub service(string $service, ?string $connection = null)
 */
class Grpc extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'grpc.client';
    }
}
