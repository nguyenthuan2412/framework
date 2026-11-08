<?php

namespace Ryra\Core\ApiDocs;

use Ryra\Core\Container\ServiceProvider;

class ApiDocsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register OpenApiGenerator as singleton
        $this->app->singleton(OpenApiGenerator::class, function () {
            $config = config('api_docs', []);

            return new OpenApiGenerator(
                title: $config['title'] ?? 'API Documentation',
                version: $config['version'] ?? '1.0.0',
                description: $config['description'] ?? 'API documentation generated from attributes',
                servers: $config['servers'] ?? [
                    ['url' => env('APP_URL', 'http://localhost'), 'description' => 'API Server']
                ]
            );
        });
    }

    public function boot(): void
    {
        // Register API documentation routes
        if (config('api_docs.enabled', true)) {
            $routeCollection = $this->app->get('route.collection');
            $routeCollection->register(ScalarController::class);
        }
    }
}
