<?php

namespace Ryra\Core\Websocket\Server;

use Ryra\Core\Container\ServiceProvider;
use Ryra\Core\Websocket\Server\Attributes\Route;
use Ryra\Core\Websocket\Server\Security;

class WebsocketServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Security::class, fn () => new Security());
    }

    public function boot(): void
    {
        $this->app->get('module.manager')->addExtend('websocket_controllers', function (mixed $controllers) {
            $routeCollection = $this->app->get('route.collection');

            foreach ($controllers as $controller) {
                $rfController = new \ReflectionClass($controller);
                $routeAttribute = $rfController->getAttributes(Route::class);
                if (!empty($routeAttribute)) {
                    $routeAttribute = $routeAttribute[0]->newInstance();
                    $route = new \Ryra\Core\Route\Route(['action' => [$controller]]);
                    $routeCollection->addRoute('GET', $routeAttribute->getUri(), $route);
                }
            }
        });
    }
}