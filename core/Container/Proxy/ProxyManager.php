<?php

namespace Ryra\Core\Container\Proxy;

class ProxyManager
{
    private array $cache = [];

    public function __construct(private ProxyGenerator $proxyGenerator) {}

    public function getProxy(string $class): object
    {
        if (!isset($this->cache[$class])) {
            $this->cache[$class] = $this->buildProxy($class);
        }

        return new $this->cache[$class]();
    }

    private function buildProxy(string $class): string
    {
        return $this->proxyGenerator->generate($class);
    }
}