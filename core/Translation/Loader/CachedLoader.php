<?php

namespace Ryra\Core\Translation\Loader;

use \Ryra\Core\Contracts\Translation\Loader;
use Psr\SimpleCache\CacheInterface;

class CachedLoader implements Loader
{
    /**
     * Create a new cached loader instance.
     *
     * @param Loader $loader The underlying loader
     * @param CacheInterface $cache The cache store
     * @param int $ttl Cache TTL in seconds
     * @param string $prefix Cache key prefix
     */
    public function __construct(
        protected Loader $loader,
        protected CacheInterface $cache,
        protected int $ttl = 3600,
        protected string $prefix = 'trans:'
    ) {}

    /**
     * {@inheritdoc}
     */
    public function load(string $locale, string $namespace): array
    {
        $cacheKey = $this->getCacheKey($locale, $namespace);

        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $translations = $this->loader->load($locale, $namespace);

        $this->cache->set($cacheKey, $translations, $this->ttl);

        return $translations;
    }

    /**
     * {@inheritdoc}
     */
    public function namespaces(string $locale): array
    {
        $cacheKey = $this->prefix . 'namespaces:' . $locale;

        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $namespaces = $this->loader->namespaces($locale);

        $this->cache->set($cacheKey, $namespaces, $this->ttl);

        return $namespaces;
    }

    /**
     * {@inheritdoc}
     */
    public function addPath(string $path, ?string $namespace = null): void
    {
        $this->loader->addPath($path, $namespace);
    }

    /**
     * Flush the cache for specific locale/namespace or all.
     *
     * @param string|null $locale
     * @param string|null $namespace
     * @return bool
     */
    public function flush(?string $locale = null, ?string $namespace = null): bool
    {
        if ($locale !== null && $namespace !== null) {
            return $this->cache->delete($this->getCacheKey($locale, $namespace));
        }

        // For full flush, clear all translation cache
        // Note: This requires pattern-based deletion which may not be available
        // in all cache drivers. Using clear() as fallback.
        return $this->cache->clear();
    }

    /**
     * Get the underlying loader.
     */
    public function getLoader(): Loader
    {
        return $this->loader;
    }

    /**
     * Get the cache key for a locale/namespace combination.
     *
     * @param string $locale
     * @param string $namespace
     * @return string
     */
    protected function getCacheKey(string $locale, string $namespace): string
    {
        return $this->prefix . $locale . ':' . $namespace;
    }
}
