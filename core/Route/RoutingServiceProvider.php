<?php

namespace Ryra\Core\Route;

use FastRoute\DataGenerator\GroupCountBased;
use Ryra\Core\Container\ServiceProvider;
use FastRoute\RouteParser\Std;
use Ryra\Core\Module\Module;
use Ryra\Core\Route\Versioning\VersionResolver;

class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            VersionResolver::class,
            fn() => new VersionResolver(\config('routing.versioning', []))
        );

        $this->app->singleton(
            'route.collection',
            fn() => new RouteCollection(
                new Std(),
                new GroupCountBased(),
                $this->app->get(VersionResolver::class)
            )
        );

        $this->app->scoped(
            'route.dispatcher',
            fn() => new RouteDispatcher(
                $this->app->get('route.collection'),
                $this->app->get('stack_middleware'),
                $this->app->get('middleware.collection'),
                $this->app->get(VersionResolver::class)
            )
        );

        $this->app->singleton(
            RouteUrlGenerator::class,
            fn() => new RouteUrlGenerator($this->app->get('route.collection'))
        );
    }

    public function boot(): void
    {
        $this->app->get('module.manager')->addExtend('controllers', function (mixed $controllers, Module $module) {
            $routeCollection = $this->app->get('route.collection');
            foreach ($controllers as $controller) {
                $routeCollection->register($controller, $module);
            }
        });
    }
}