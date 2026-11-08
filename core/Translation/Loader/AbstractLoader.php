<?php

namespace Ryra\Core\Translation\Loader;

abstract class AbstractLoader implements \Ryra\Core\Contracts\Translation\Loader
{
    /**
     * Registered paths with optional namespace prefixes.
     * Key is namespace (or '*' for wildcard), value is array of paths.
     *
     * @var array<string, array<string>>
     */
    protected array $paths = [];

    /**
     * Loaded translations cache.
     * Structure: [locale][namespace] => translations array
     *
     * @var array<string, array<string, array>>
     */
    protected array $loaded = [];

    /**
     * {@inheritdoc}
     */
    public function addPath(string $path, ?string $namespace = null): void
    {
        $key = $namespace ?? '*';

        if (!isset($this->paths[$key])) {
            $this->paths[$key] = [];
        }

        $this->paths[$key][] = rtrim($path, '/\\');
    }

    /**
     * {@inheritdoc}
     */
    public function namespaces(string $locale): array
    {
        $namespaces = [];

        foreach ($this->paths as $namespace => $paths) {
            foreach ($paths as $path) {
                $discovered = $this->discoverNamespaces($path, $locale);
                $namespaces = array_merge($namespaces, $discovered);
            }
        }

        return array_unique($namespaces);
    }

    /**
     * Get paths for a specific namespace.
     *
     * @param string $namespace
     * @return array<string>
     */
    protected function getPathsForNamespace(string $namespace): array
    {
        // Check for namespace-specific paths first
        if (isset($this->paths[$namespace])) {
            return $this->paths[$namespace];
        }

        // Fall back to wildcard paths
        return $this->paths['*'] ?? [];
    }

    /**
     * Check if translations for locale/namespace are already loaded.
     *
     * @param string $locale
     * @param string $namespace
     * @return bool
     */
    protected function isLoaded(string $locale, string $namespace): bool
    {
        return isset($this->loaded[$locale][$namespace]);
    }

    /**
     * Cache loaded translations.
     *
     * @param string $locale
     * @param string $namespace
     * @param array $translations
     * @return array
     */
    protected function cacheTranslations(string $locale, string $namespace, array $translations): array
    {
        if (!isset($this->loaded[$locale])) {
            $this->loaded[$locale] = [];
        }

        return $this->loaded[$locale][$namespace] = $translations;
    }

    /**
     * Get cached translations.
     *
     * @param string $locale
     * @param string $namespace
     * @return array|null
     */
    protected function getCached(string $locale, string $namespace): ?array
    {
        return $this->loaded[$locale][$namespace] ?? null;
    }

    /**
     * Clear the loaded translations cache.
     *
     * @param string|null $locale
     * @param string|null $namespace
     * @return void
     */
    public function clearCache(?string $locale = null, ?string $namespace = null): void
    {
        if ($locale === null) {
            $this->loaded = [];
            return;
        }

        if ($namespace === null) {
            unset($this->loaded[$locale]);
            return;
        }

        unset($this->loaded[$locale][$namespace]);
    }

    /**
     * Discover available namespaces from the given path.
     *
     * @param string $path
     * @param string $locale
     * @return array<string>
     */
    abstract protected function discoverNamespaces(string $path, string $locale): array;

    /**
     * Read and parse the translation file.
     *
     * @param string $filePath
     * @return array
     */
    abstract protected function readFile(string $filePath): array;

    /**
     * Build the file path for a translation file.
     *
     * @param string $path Base path
     * @param string $locale Locale code
     * @param string $namespace Namespace/group name
     * @return string
     */
    abstract protected function buildFilePath(string $path, string $locale, string $namespace): string;
}
