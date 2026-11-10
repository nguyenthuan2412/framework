<?php

namespace Ryra\Core\Container;

use Ryra\Core\Contracts\Container\Container as ContractContainer;
use Ryra\Core\Container\Exception\ServiceNotFoundException;
use Ryra\Core\Container\Proxy\ProxyManager;
use Ryra\Core\Container\AOP\AopProxy;
use Ryra\Core\Container\AOP\AopProxyManager;
use Ryra\Core\Container\AOP\AspectCollector;
use Swoole\Coroutine;
use ArrayAccess;

class Container implements ContractContainer, ArrayAccess
{
    private static ?ContractContainer $instance = null;

    protected static ?ProxyManager $proxyManager = null;

    protected static ?AopProxyManager $aopProxyManager = null;

    /**
     * @var array<string, object>
     */
    protected array $bindings = [];

    /**
     * @var array<string, object>
     */
    protected array $singletionInstances = [];


    /**
     * @var array<string, object>
     */
    protected array $scopedInstances = [];

    /**
     * @var string[]
     */
    protected array $aliases = [];

    /**
     * Singletons constructor should always be private.
     */
    protected function __construct()
    {
        $this->singleton('service_provider_manager', fn () => new ServiceProviderManager($this));
    }

    /**
     * Get the globally available instance of the container.
     */
    public static function getInstance(): static
    {
        if (\is_null(static::$instance)) {
            static::$instance = new static;
            static::$instance->singleton('container', static fn () => static::$instance);
            static::$instance->alias('container', \Ryra\Core\Contracts\Container\Container::class);
        }
        return static::$instance;
    }

    /**
     * Register a shared binding in the container.
     */
    public function singleton(string $id, string|callable $callback): void
    {
        if (\is_string($callback)) {
            $callback = static fn() => new (static::resolveClass($callback))();
        }
        $this->bindings[__FUNCTION__][$id] = $callback;
    }

    /**
     * Register a shared binding if it hasn't already been registered.
     */
    public function singletonIf(string $id, string|callable $callback): void
    {
        if (!$this->has($id)) {
            $this->singleton($id, $callback);
        }
    }

    /**
     * Register a transient binding in the container.
     */
    public function transient(string $id, string|callable $callback): void
    {
        if (\is_string($callback)) {
            $callback = static fn() => new (static::resolveClass($callback))();
        }
        $this->bindings[__FUNCTION__][$id] = $callback;
    }

    /**
     * Register a transient binding if it hasn't already been registered.
     */
    public function transientIf(string $id, string|callable $callback): void
    {
        if (!$this->has($id)) {
            $this->transient($id, $callback);
        }
    }

    /**
     * Register a scoped binding in the container.
     */
    public function scoped(string $id, string|callable $callback): void
    {
        if (\is_string($callback)) {
            $callback = static fn() => new (static::resolveClass($callback))();
        }
        $this->bindings[__FUNCTION__][$id] = $callback;
    }

    /**
     * Register a scoped binding if it hasn't already been registered.
     */
    public function scopedIf(string $id, callable $callback): void
    {
        if (!$this->has($id)) {
            $this->scoped($id, $callback);
        }
    }

    /**
     * Get service by id.
     */
    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new ServiceNotFoundException("Service $id does not exist");
        }

        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }

        if (isset($this->singletionInstances[$id])) {
            return $this->singletionInstances[$id];
        }

        if ($this->hasScopedInstance($id)) {
            return $this->getScopedInstance($id);
        }

        return $this->createService($id);
    }

    protected function hasScopedInstance(string $id): bool
    {
        $cid = Coroutine::getuid();
        if ($cid < 0) {
            return isset($this->scopedInstances[$id]);
        }

        return isset($this->scopedInstances[$cid][$id]);
    }

    protected function getScopedInstance(string $id): mixed
    {
        $cid = Coroutine::getuid();
        if ($cid < 0) {
            return $this->scopedInstances[$id];
        }

        return $this->scopedInstances[$cid][$id];
    }

    /**
     * Check service exists.
     */
    public function has(string $id): bool
    {
        return isset($this->bindings['singleton'][$id])
            || isset($this->bindings['transient'][$id])
            || isset($this->bindings['scoped'][$id])
            || isset($this->aliases[$id]);
    }

    /**
     * Alias a type to a different name.
     */
    public function alias(string $id, string $alias): static
    {
        if ($alias === $id) {
            throw new \LogicException("[{$id}] is aliased to itself.");
        }
        $this->aliases[$alias] = $id;
        return $this;
    }

    /**
     * Get the alias for an abstract if available.
     */
    public function getAlias(string $id): array
    {
        $aliases = [];
        foreach ($this->aliases as $alias => $serviceId) {
            if ($id === $serviceId) {
                $aliases[] = $alias;
            }
        }
        return $aliases;
    }

    /**
     * Clear all of the scoped instances from the container.
     */
    public function forgetScopedInstances(): void
    {
        $cid = Coroutine::getuid();
        if ($cid < 0) {
            foreach (\array_keys($this->scopedInstances) as $id) {
                unset($this->scopedInstances[$id]);
            }
            return;
        }

        unset($this->scopedInstances[$cid]);
    }

    /**
     * Create and return one or more services.
     */
    protected function createService(string $id): mixed
    {
        if (!empty($this->bindings['singleton'][$id])) {
            $service = $this->bindings['singleton'][$id]($this);
            $this->singletionInstances[$id] = $service;
        } else if (!empty($this->bindings['scoped'][$id])) {
            $service = $this->bindings['scoped'][$id]($this);
            $cid = Coroutine::getuid();
            if ($cid < 0) {
                $this->scopedInstances[$id] = $service;
            } else {
                $this->scopedInstances[$cid][$id] = $service;
            }
        } else {
            $service = $this->bindings['transient'][$id]($this);
        }

        if (\is_object($service)) {
            static::injectProperties($service);
        }
        return $service;
    }

    /**
     * Bật proxy sinh sẵn (bỏ Reflection khỏi make()/injectProperties()). null = tắt.
     */
    public static function setProxyManager(?ProxyManager $proxyManager): void
    {
        static::$proxyManager = $proxyManager;
    }

    public static function getProxyManager(): ?ProxyManager
    {
        return static::$proxyManager;
    }

    /**
     * Quản lý class AOP (class con sinh sẵn có chèn aspect). null = tắt AOP.
     */
    public static function setAopProxyManager(?AopProxyManager $aopProxyManager): void
    {
        static::$aopProxyManager = $aopProxyManager;
    }

    public static function getAopProxyManager(): ?AopProxyManager
    {
        return static::$aopProxyManager;
    }

    /**
     * Danh sách aspect. Tự bật AOP (eval, không ghi file) nếu chưa có AopProxyManager.
     */
    public static function aspects(): AspectCollector
    {
        return (static::$aopProxyManager ??= AopProxyManager::create(new AspectCollector()))->getAspects();
    }

    /**
     * Class thực sự cần `new`: class AOP nếu có aspect cắt vào, ngược lại là chính nó.
     */
    public static function resolveClass(string $class): string
    {
        return static::$aopProxyManager?->proxyFor($class) ?? $class;
    }

    public static function make(string $concrete, array $parameters = []): object
    {
        $aop = static::$aopProxyManager?->proxyFor($concrete);

        if (static::$proxyManager && ($proxy = static::$proxyManager->proxyFor($concrete))) {
            return $proxy::make(static::getInstance(), $parameters, $aop);
        }

        $object = new ($aop ?? $concrete)(...static::getObjectDependencies($concrete, $parameters));
        static::injectProperties($object);
        return $object;
    }

    public static function call(\Closure|array $callback, array $parameters = []): mixed
    {
        if (\is_array($callback)) {
            $concrete = $callback[0];
            $method = $callback[1];
            if (\is_string($concrete)) {
                $container = static::getInstance();
                if ($container->has($concrete)) {
                    $concrete = $container->get($concrete);
                    $callback[0] = $concrete;
                } else {
                    $concrete = static::make($concrete);
                }
            }

            $parameters = static::getMethodDependencies($callback, $parameters);
            return $concrete->$method(...$parameters);
        }

        $parameters = static::getMethodDependencies($callback, $parameters);
        return $callback(...$parameters);
    }

    protected static function getMethodDependencies(\Closure|array $callback, array $parameters = []): array
    {
        if (!empty($parameters) && \array_is_list($parameters)) {
            return $parameters;
        }

        if (\is_array($callback)) {
            $reflection = new \ReflectionMethod($callback[0], $callback[1]);
        } else {
            $reflection = new \ReflectionFunction($callback);
        }

        return static::getMethodParameters($reflection, $parameters);
    }

    protected static function getObjectDependencies(string $class, array $parameters = []): array
    {
        $reflection = new \ReflectionClass($class);
        $construct = '__construct';
        if ($reflection->hasMethod($construct)) {
            $reflectionMethod = $reflection->getMethod($construct);
            return static::getMethodParameters($reflectionMethod, $parameters);
        }
        return [];
    }

    protected static function getMethodParameters(\ReflectionFunctionAbstract $reflectionMethod, array $parameters = []): array
    {
        $container = static::getInstance();
        $params = [];
        foreach ($reflectionMethod->getParameters() as $param) {
            $paramType = (string)$param->getType();
            $paramName = $param->getName();
            if ($container->has($paramType)) {
                $params[$paramName] = $container->get($paramType);
            } else if ($param->isOptional()) {
                $params[$paramName] = $param->getDefaultValue();
            } else {
                $params[$paramName] = null;
            }
        }

        return \array_values(\array_merge($params, $parameters));
    }

    protected static function injectProperties(object $object): void
    {
        // Object AOP: inject theo class gốc (class con không thấy property private của class cha).
        $class = $object instanceof AopProxy ? $object::AOP_TARGET : $object::class;

        if (static::$proxyManager && ($proxy = static::$proxyManager->proxyFor($class))) {
            $proxy::inject($object, static::getInstance());
            return;
        }

        $properties = (new \ReflectionClass($class))->getProperties();
        if (!empty($properties)) {
            foreach ($properties as $property) {
                if (
                    (
                        !$property->isInitialized($object)
                        || empty($property->getValue($object))
                    )
                    && !empty($attributes = $property->getAttributes(Inject::class))
                ) {
                    $instance = (string) \end($attributes)->newInstance();
                    if ($instance === '') {
                        $instance = (string) $property->getType();
                    }
                    $property->setValue($object, static::getInstance()->get($instance));
                }
            }
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->transient($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $cid = Coroutine::getuid();
        unset($this->bindings[$offset], $this->singletionInstances[$offset], $this->scopedInstances[$cid][$offset]);
    }

    public function copyScopedInstances(int $parentId, int $childId): void
    {
        if (!isset($this->scopedInstances[$parentId])) {
            return;
        }

        $this->scopedInstances[$childId] = $this->scopedInstances[$parentId];
    }

    /**
     * Singletons should not be cloneable.
     */
    protected function __clone(): void
    {
    }

    /**
     * Singletons should not be restorable from strings.
     */
    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize a singleton.');
    }
}