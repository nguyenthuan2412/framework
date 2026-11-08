<?php

namespace Ryra\Core\Support;

use Ryra\Core\Container\Exception\ServiceNotFoundException;
use Ryra\Core\App;

abstract class Facade
{
    abstract protected static function getFacadeAccessor(): string|object;

    public static function __callStatic(string $name, array $arguments): mixed
    {
        $accesscor = static::getFacadeAccessor();
        if (is_string($accesscor)) {
            $container = App::getInstance();
            if ($container->has($accesscor)) {
                $accesscor = $container->get($accesscor);
            } else {
                throw new ServiceNotFoundException("$accesscor service does not exist");
            }
        }
        return $accesscor->$name(...$arguments);
    }
}