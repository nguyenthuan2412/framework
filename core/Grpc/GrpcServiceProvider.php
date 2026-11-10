<?php

namespace Ryra\Core\Grpc;

use Ryra\Core\Container\ServiceProvider;
use Ryra\Core\Grpc\Client\ClientManager;
use Ryra\Core\Grpc\Command\DtoCommand;
use Ryra\Core\Grpc\Command\ExportProtoCommand;
use Ryra\Core\Grpc\Command\ListCommand;
use Ryra\Core\Grpc\Health\HealthService;
use Ryra\Core\Grpc\Protobuf\Codec;
use Ryra\Core\Grpc\Server\GrpcKernel;
use Ryra\Core\Grpc\Server\ServiceRegistry;
use Ryra\Core\Module\Module;

class GrpcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('grpc.codec', fn () => new Codec(base_path(\join_paths('storage', 'runtime', 'grpc'))));
        $this->app->alias('grpc.codec', Codec::class);

        $this->app->singleton('grpc.registry', function () {
            $registry = new ServiceRegistry();
            if (config('grpc.health', true)) {
                $registry->register(HealthService::class, exportProto: false);
            }
            foreach ((array) config('grpc.services', []) as $service) {
                $registry->register($service);
            }
            return $registry;
        });
        $this->app->alias('grpc.registry', ServiceRegistry::class);

        $this->app->singleton('grpc.kernel', fn () => new GrpcKernel(
            registry: $this->app->get('grpc.registry'),
            codec: $this->app->get('grpc.codec'),
            interceptors: (array) config('grpc.interceptors', []),
            debug: (bool) config('app.debug', false),
        ));
        $this->app->alias('grpc.kernel', GrpcKernel::class);

        $this->app->singleton('grpc.client', fn () => new ClientManager(
            codec: $this->app->get('grpc.codec'),
            config: (array) config('grpc.clients', []),
            default: (string) config('grpc.default_client', 'default'),
        ));
        $this->app->alias('grpc.client', ClientManager::class);
    }

    public function boot(): void
    {
        $console = $this->app->get('console');
        $console->register(DtoCommand::class);
        $console->register(ExportProtoCommand::class);
        $console->register(ListCommand::class);

        // Khai báo trong *.module.php: 'grpc_services' => [UserService::class]
        $this->app->get('module.manager')->addExtend('grpc_services', function (array $services, Module $module) {
            $registry = $this->app->get('grpc.registry');
            foreach ($services as $service) {
                $registry->register($service);
            }
        });
    }
}
