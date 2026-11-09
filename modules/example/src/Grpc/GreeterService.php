<?php

namespace Ryra\Modules\Example\Grpc;

use Ryra\Core\Container\Inject;
use Ryra\Core\Grpc\Attributes\Rpc;
use Ryra\Core\Grpc\Attributes\Service;
use Ryra\Core\Grpc\Exception\GrpcException;
use Ryra\Core\Grpc\Server\ServerContext;
use Ryra\Modules\Example\Services\GreetingService;

#[Service(package: 'example.v1', comment: 'Service ví dụ')]
class GreeterService
{
    #[Inject]
    protected GreetingService $greeting;

    public function sayHello(HelloRequest $request, ServerContext $context): HelloReply
    {
        if ($request->name === '') {
            throw GrpcException::invalidArgument('name is required');
        }

        return new HelloReply($this->greeting->greet($request->name), new \DateTimeImmutable());
    }

    #[Rpc(response: HelloReply::class, comment: 'Trả về nhiều lời chào (server streaming)')]
    public function sayHelloStream(HelloRequest $request): \Generator
    {
        for ($i = 1; $i <= $request->times; $i++) {
            yield new HelloReply("#$i " . $this->greeting->greet($request->name));
        }
    }
}
