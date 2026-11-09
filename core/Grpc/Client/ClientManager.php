<?php

namespace Ryra\Core\Grpc\Client;

use Ryra\Core\Grpc\Protobuf\Codec;

/**
 * Quản lý các kết nối client khai báo trong config/grpc.php (mục clients).
 */
final class ClientManager
{
    /** @var array<string, GrpcClient> */
    private array $clients = [];

    public function __construct(
        private readonly Codec $codec,
        private readonly array $config = [],
        private readonly string $default = 'default',
    ) {}

    public function connection(?string $name = null): GrpcClient
    {
        $name ??= $this->default;
        if (isset($this->clients[$name])) {
            return $this->clients[$name];
        }
        $config = $this->config[$name] ?? throw new \InvalidArgumentException("gRPC client [$name] is not configured");
        $address = $config['address'] ?? throw new \InvalidArgumentException("gRPC client [$name] is missing address");

        return $this->clients[$name] = new GrpcClient($address, $this->codec, $config);
    }

    /**
     * Tạo client tới một địa chỉ bất kỳ (không cần khai báo trong config).
     */
    public function to(string $address, array $options = []): GrpcClient
    {
        return new GrpcClient($address, $this->codec, $options);
    }

    /**
     * @template T
     * @param class-string<T> $service
     * @return T&ServiceStub
     */
    public function service(string $service, ?string $connection = null): ServiceStub
    {
        return $this->connection($connection)->service($service);
    }
}
