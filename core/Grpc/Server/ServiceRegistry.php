<?php

namespace Ryra\Core\Grpc\Server;

use Ryra\Core\Grpc\Exception\MetadataException;
use Ryra\Core\Grpc\Metadata\MetadataFactory;
use Ryra\Core\Grpc\Metadata\MethodMetadata;
use Ryra\Core\Grpc\Metadata\ServiceMetadata;

/**
 * Danh sách service gRPC đã đăng ký, tra cứu theo path /package.Service/Method.
 */
final class ServiceRegistry
{
    /** @var array<string, array{0: string, 1: ServiceMetadata}> fullName => [implementation, metadata] */
    private array $services = [];

    /** @var array<string, array{0: string, 1: ServiceMetadata, 2: MethodMetadata}> */
    private array $routes = [];

    /**
     * @param string $implementation Class xử lý (có thể implement interface mang #[Service]).
     * @param array<string, string> $options internal: không sinh .proto cho service này.
     */
    public function register(string $implementation, bool $exportProto = true): ServiceMetadata
    {
        $metadata = MetadataFactory::forService($implementation);
        $fullName = $metadata->fullName();

        if (isset($this->services[$fullName]) && $this->services[$fullName][0] !== $implementation) {
            throw new MetadataException(
                "gRPC service [$fullName] is already registered by [{$this->services[$fullName][0]}]"
            );
        }
        if (!\class_exists($implementation) || (new \ReflectionClass($implementation))->isAbstract()) {
            throw new MetadataException("gRPC service implementation [$implementation] must be a concrete class");
        }

        $this->services[$fullName] = [$implementation, $metadata, $exportProto];
        foreach ($metadata->methods as $method) {
            $this->routes[$metadata->path($method)] = [$implementation, $metadata, $method];
        }

        return $metadata;
    }

    /**
     * @return array{0: string, 1: ServiceMetadata, 2: MethodMetadata}|null
     */
    public function find(string $path): ?array
    {
        return $this->routes[$path] ?? null;
    }

    public function has(string $fullName): bool
    {
        return isset($this->services[$fullName]);
    }

    /**
     * @return ServiceMetadata[]
     */
    public function all(bool $onlyExportable = false): array
    {
        $services = [];
        foreach ($this->services as $fullName => [, $metadata, $export]) {
            if (!$onlyExportable || $export) {
                $services[$fullName] = $metadata;
            }
        }
        return $services;
    }

    public function isEmpty(): bool
    {
        return $this->services === [];
    }
}
