<?php

namespace Ryra\Core\Container\Proxy;

/**
 * Trả về tên class proxy (đã nạp) cho một class, sinh file nếu chưa có.
 *
 * Kết quả được cache trong RAM theo worker: chỉ lần đầu gặp class mới dùng Reflection
 * và đọc file, các lần sau chỉ là tra mảng.
 */
class ProxyManager
{
    /**
     * @var array<string, string|false> class => FQCN proxy, hoặc false nếu class không dùng proxy.
     */
    private array $cache = [];

    public function __construct(
        private ProxyFileLocator $locator,
        private ProxyGenerator $generator = new ProxyGenerator(),
    ) {}

    public static function create(string $proxyFolder): static
    {
        return new static(new ProxyFileLocator($proxyFolder));
    }

    /**
     * FQCN của proxy, hoặc null nếu class phải đi đường Reflection.
     */
    public function proxyFor(string $class): ?string
    {
        if (!isset($this->cache[$class])) {
            $this->cache[$class] = $this->load($class) ?? false;
        }

        return $this->cache[$class] ?: null;
    }

    /**
     * Xoá cache trong RAM (file proxy vẫn giữ).
     */
    public function flush(): void
    {
        $this->cache = [];
    }

    private function load(string $class): ?string
    {
        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException) {
            return null;
        }

        if ($reflection->isAnonymous() || $reflection->isInternal()) {
            return null;
        }

        $proxyClassName = $this->locator->getProxyClassName($reflection);
        $fqcn = ProxyFileLocator::NAMESPACE . '\\' . $proxyClassName;
        if (\class_exists($fqcn, false)) {
            return $fqcn;
        }

        $file = $this->locator->getProxyFile($proxyClassName);
        if (!\is_file($file) && !$this->write($reflection, $proxyClassName, $file)) {
            return null;
        }

        require_once $file;

        return \class_exists($fqcn, false) ? $fqcn : null;
    }

    private function write(\ReflectionClass $reflection, string $proxyClassName, string $file): bool
    {
        $code = $this->generator->generate($reflection, $proxyClassName);
        if ($code === null) {
            return false;
        }

        return $this->locator->write($file, $code, $this->locator->getProxyFilePattern($reflection->getName()));
    }
}
