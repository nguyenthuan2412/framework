<?php

namespace Ryra\Core\Grpc\Metadata;

final class ServiceMetadata
{
    /** @var array<string, MethodMetadata> theo tên rpc */
    public readonly array $methods;

    /** @var array<string, MethodMetadata> theo tên method PHP */
    public readonly array $methodsByPhpName;

    /**
     * @param string $class Class/interface có attribute #[Service].
     * @param MethodMetadata[] $methods
     */
    public function __construct(
        public readonly string $class,
        public readonly string $name,
        public readonly string $package,
        array $methods,
        public readonly ?string $comment = null,
    ) {
        $byName = [];
        $byPhp = [];
        foreach ($methods as $method) {
            $byName[$method->name] = $method;
            $byPhp[$method->phpMethod] = $method;
        }
        $this->methods = $byName;
        $this->methodsByPhpName = $byPhp;
    }

    public function fullName(): string
    {
        return $this->package === '' ? $this->name : "{$this->package}.{$this->name}";
    }

    public function path(MethodMetadata $method): string
    {
        return '/' . $this->fullName() . '/' . $method->name;
    }
}
