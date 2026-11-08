<?php

namespace Ryra\Core\Container;

class ServiceProviderManager
{
    protected array $providers = [];

    public function __construct(protected \Ryra\Core\Contracts\Container\Container $container) {}

    public function register(string $class): void
    {
        if (\is_subclass_of($class, ServiceProvider::class) && !\in_array($class, $this->providers)) {
            $provider = new $class($this->container);
            $provider->register();
            $this->providers[$class] = $provider;
        }
    }

    public function boot(?string $class = null): void
    {
        if (\is_null($class)) {
            foreach ($this->providers as $class => $provider) {
                $provider->boot();
                unset($this->providers[$class]);
            }
            return;
        }

        if (!empty($this->providers[$class])) {
            $provider = $this->providers[$class];
            $provider->boot();
            unset($this->providers[$class]);
        }
    }
}