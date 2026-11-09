<?php

namespace Ryra\Core\Grpc\Client;

use Ryra\Core\Grpc\Metadata\ServiceMetadata;

/**
 * Proxy gọi rpc theo signature của service class/interface.
 *
 *   $users = $client->service(UserServiceInterface::class);
 *   $user = $users->getUser(new GetUserRequest(1));
 *   $user = $users->withMetadata(['authorization' => "Bearer $token"])->getUser(...);
 */
final class ServiceStub
{
    public function __construct(
        private readonly GrpcClient $client,
        private readonly ServiceMetadata $service,
        private readonly array $metadata = [],
        private readonly ?float $timeout = null,
    ) {}

    public function withMetadata(array $metadata): self
    {
        return new self($this->client, $this->service, $metadata + $this->metadata, $this->timeout);
    }

    public function withTimeout(float $seconds): self
    {
        return new self($this->client, $this->service, $this->metadata, $seconds);
    }

    public function __call(string $name, array $arguments): mixed
    {
        $method = $this->service->methodsByPhpName[$name]
            ?? throw new \BadMethodCallException("Rpc [$name] does not exist on service [{$this->service->fullName()}]");

        $path = $this->service->path($method);
        $request = $arguments[0] ?? new $method->requestClass();

        if ($method->clientStreaming) {
            return $this->client->clientStream($path, $request, $method->responseClass, $this->metadata, $this->timeout);
        }
        if ($method->serverStreaming) {
            return $this->client->serverStream($path, $request, $method->responseClass, $this->metadata, $this->timeout);
        }

        $response = $this->client->unary($path, $request, $method->responseClass, $this->metadata, $this->timeout);
        return $method->returnsVoid ? null : $response;
    }
}
