<?php

namespace Ryra\Core\Grpc\Health;

use Ryra\Core\App;
use Ryra\Core\Grpc\Attributes\Service;
use Ryra\Core\Grpc\Exception\GrpcException;
use Ryra\Core\Grpc\Server\Code;

/**
 * gRPC Health Checking Protocol - https://github.com/grpc/grpc/blob/master/doc/health-checking.md
 *
 * Mặc định mọi service đã đăng ký đều là SERVING. Đổi trạng thái bằng:
 *   HealthService::setStatus('user.v1.UserService', ServingStatus::NOT_SERVING);
 */
#[Service(name: 'Health', package: 'grpc.health.v1')]
final class HealthService
{
    /** @var array<string, ServingStatus> */
    private static array $statuses = [];

    public static function setStatus(string $service, ServingStatus $status): void
    {
        self::$statuses[$service] = $status;
    }

    public function check(HealthCheckRequest $request): HealthCheckResponse
    {
        $service = $request->service;
        if (isset(self::$statuses[$service])) {
            return new HealthCheckResponse(self::$statuses[$service]);
        }
        if ($service === '' || App::service('grpc.registry')->has($service)) {
            return new HealthCheckResponse(ServingStatus::SERVING);
        }
        throw new GrpcException(Code::NOT_FOUND, "Unknown service $service");
    }
}
