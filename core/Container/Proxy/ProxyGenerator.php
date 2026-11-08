<?php

namespace Ryra\Core\Container\Proxy;

class ProxyGenerator
{
    public function __construct(private ProxyFileLocator $proxyFileLocator) {}

    public function generate(string $class): string
    {
        $proxyFile = $this->proxyFileLocator->getProxyFile($class);
        $className = $this->proxyFileLocator->getProxyClassName($class);

        $content =
"
<?php

namespace Ryra\Runtime\Proxy;

class $className
{
    public \$instance = null;

    public function __construct()
    {
        
    }
}
";

        file_put_contents($proxyFile, $content);
        return "\Ryra\Runtime\Proxy\\$className";
    }
}