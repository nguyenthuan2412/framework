<?php

namespace Ryra\Core\Macro;

use BadMethodCallException;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

trait Macroable
{
    /**
     * The registered macros.
     *
     * @var array<Closure>
     */
    protected static $macros = [];

    /**
     * Register a macro.
     *
     * @param string $name
     * @param callable $callback
     * @return void
     */
    public static function macro(string $name, callable $callback): void
    {
        static::$macros[$name] = $callback;
    }

    /**
     * Mix another object into the class.
     *
     * @param object $mixin
     * @param bool $replace
     * @return void
     *
     * @throws \LogicException
     * @throws \RuntimeException
     */
    public static function mixin(object $mixin, bool $replace = true): void
    {
        $reflectionClass = new ReflectionClass($mixin);
        $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if (!$replace && static::hasMacro($method->name)) {
                throw new LogicException("Can't register two functions {$method->name}");
            }
            
            if ($replace || !static::hasMacro($method->name)) {
                $returnType = (string)$method->getReturnType();
                if ($returnType === 'Closure' || $returnType === 'callable') {
                    static::macro($method->name, $method->invoke($mixin));
                } else {
                    throw new RuntimeException(sprintf(
                        'The %s::%s must declare return a Closure', $reflectionClass->getName(), $method->name
                    ));
                }
            }
        }
    }

    /**
     * Check if the macros is attached.
     *
     * @param string $name
     * @return bool
     */
    public static function hasMacro(string $name): bool
    {
        return isset(static::$macros[$name]);
    }

    /**
     * Remove macro.
     *
     * @param string $name
     * @return void
     */
    public static function removeMacro(string $name): void
    {
        unset(static::$macros[$name]);
    }

    /**
     * Remove macros.
     *
     * @return void
     */
    public static function clear(): void
    {
        static::$macros = [];
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public static function __callStatic(string $method, array $parameters = []): mixed
    {
        if (!static::hasMacro($method)) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.', static::class, $method
            ));
        }

        $plug = static::$macros[$method];
        $plug = $plug->bindTo(null, static::class);
        
        return $plug(...$parameters);
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call(string $method, array $parameters = []): mixed
    {
        if (!static::hasMacro($method)) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.', static::class, $method
            ));
        }

        $plug = static::$macros[$method];
        $plug = $plug->bindTo($this, static::class);

        return $plug(...$parameters);
    }
}