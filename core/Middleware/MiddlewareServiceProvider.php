<?php

namespace Ryra\Core\Middleware;

use Ryra\Core\Container\ServiceProvider;
use Ryra\Core\Module\Module;

class MiddlewareServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            'middleware.collection',
            fn() => new MiddlewareCollection($this->app->get('module.manager'))
        );
        $this->app->alias('middleware.collection', MiddlewareCollection::class);
        $this->app->scoped('stack_middleware', fn() => new StackMiddleware);
    }

    public function boot(): void
    {
        $this->app->get('module.manager')->addExtend('middlewares', function (mixed $middlewares, Module $module) {
            $collection = $this->app->get('middleware.collection');
            foreach ($middlewares as $middleware) {
                $collection->add($middleware, $module->getId());
            }
        });
    }
}