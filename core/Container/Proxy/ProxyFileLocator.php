<?php

namespace Ryra\Core\Container\Proxy;

class ProxyFileLocator
{
    private string $proxyFolder;

    public function __construct()
    {
        $this->proxyFolder = \storage_path('runtime/proxy');
        if (!\is_dir($this->proxyFolder)) {
            \mkdir($this->proxyFolder, 0755, true);
        }
    }

    public function getProxyFile(string $class): string
    {
        $className = $this->getProxyClassName($class);
        return $this->proxyFolder . DS . $className;
    }

    public function getProxyClassName(string $class): string
    {
        $hash = \substr(\hash('sha256', \serialize($class)), 0, 16);
        return (new \ReflectionClass($class))->getName() . "_{$hash}_Proxy";
    }
}