<?php

namespace Ryra\Core\Container\AOP;

use Ryra\Core\Container\Proxy\ProxyFileLocator;

/**
 * Trả về class AOP (class con sinh sẵn) cho một class, hoặc null nếu không có aspect nào cắt nó.
 *
 * Reflection chỉ chạy lần đầu gặp class trong mỗi worker; sau đó chỉ là tra mảng.
 * Có thư mục proxy thì ghi file (opcache cache được), không có thì eval (dùng cho test/CLI).
 */
class AopProxyManager
{
    /**
     * @var array<string, string|false> class => FQCN class AOP, hoặc false nếu không cần.
     */
    private array $cache = [];

    private int $revision = -1;

    public function __construct(
        private AspectCollector $aspects,
        private ?ProxyFileLocator $locator = null,
        private AopProxyGenerator $generator = new AopProxyGenerator(),
    ) {}

    public static function create(AspectCollector $aspects, ?string $proxyFolder = null): static
    {
        return new static($aspects, $proxyFolder !== null ? new ProxyFileLocator($proxyFolder) : null);
    }

    public function getAspects(): AspectCollector
    {
        return $this->aspects;
    }

    /**
     * Class cần `new` thay cho $class, hoặc null nếu dùng class gốc.
     */
    public function proxyFor(string $class): ?string
    {
        if ($this->revision !== $this->aspects->getRevision()) {
            $this->cache = [];
            $this->revision = $this->aspects->getRevision();
        }

        if (!isset($this->cache[$class])) {
            $this->cache[$class] = $this->load($class) ?? false;
        }

        return $this->cache[$class] ?: null;
    }

    public function flush(): void
    {
        $this->cache = [];
    }

    private function load(string $class): ?string
    {
        if ($this->aspects->isEmpty() || !$this->aspects->mayMatchClass(\ltrim($class, '\\'))) {
            return null;
        }

        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException) {
            return null;
        }

        $plan = $this->generator->plan($reflection, $this->aspects);
        if ($plan === []) {
            return null;
        }

        // Cấu hình aspect là một phần của phiên bản: đổi pointcut là sinh lại class.
        $extra = AopProxyGenerator::VERSION . '|' . \serialize($plan);
        $locator = $this->locator ?? new ProxyFileLocator(\sys_get_temp_dir());
        $proxyClassName = $locator->getProxyClassName($reflection, 'A', $extra);
        $fqcn = ProxyFileLocator::NAMESPACE . '\\' . $proxyClassName;
        if (\class_exists($fqcn, false)) {
            return $fqcn;
        }

        $code = $this->generator->generate($reflection, $proxyClassName, $plan);
        if ($code === null) {
            return null;
        }

        if ($this->locator === null) {
            eval(\substr($code, 5));
            return \class_exists($fqcn, false) ? $fqcn : null;
        }

        $file = $this->locator->getProxyFile($proxyClassName);
        if (!\is_file($file)) {
            $pattern = $this->locator->getProxyFilePattern($reflection->getName(), 'A');
            if (!$this->locator->write($file, $code, $pattern)) {
                return null;
            }
        }

        require_once $file;

        return \class_exists($fqcn, false) ? $fqcn : null;
    }
}
