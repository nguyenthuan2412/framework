<?php

namespace Ryra\Core\Translation;

use Ryra\Core\Support\Manager;
use Ryra\Core\Contracts\Container\Container;
use Ryra\Core\Contracts\Config\Repository;
use Ryra\Core\Contracts\Database\ConnectionManager;
use Ryra\Core\Translation\Loader\JsonLoader;
use Ryra\Core\Translation\Loader\ArrayLoader;
use Ryra\Core\Translation\Loader\DatabaseLoader;
use Ryra\Core\Translation\Loader\CachedLoader;
use Psr\SimpleCache\CacheInterface;

class TranslationManager extends Manager
{
    /**
     * The config repository instance.
     */
    protected Repository $config;

    /**
     * The database connection manager.
     */
    protected ?ConnectionManager $database = null;

    /**
     * The cached translator instance.
     */
    protected ?Translator $translator = null;

    /**
     * Create a new translation manager instance.
     */
    public function __construct(protected Container $container)
    {
        $this->config = $this->container->get('config');

        if ($this->container->has('database')) {
            $this->database = $this->container->get('database');
        }
    }

    /**
     * Get the cache driver for translations.
     *
     * @return CacheInterface|null
     */
    protected function getCacheDriver(): ?CacheInterface
    {
        if (!$this->container->has('cache')) {
            return null;
        }

        $cacheManager = $this->container->get('cache');
        $driver = $this->config->get('translation.cache.driver');

        // Nếu có chỉ định driver cụ thể, dùng driver đó
        // Nếu không, dùng driver mặc định
        return $driver ? $cacheManager->driver($driver) : $cacheManager->driver();
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('translation.driver', 'array');
    }

    /**
     * Get the translator instance.
     *
     * @param string|null $driver
     * @return Translator
     */
    public function translator(?string $driver = null): Translator
    {
        // Return cached translator if using default driver
        if ($driver === null && $this->translator !== null) {
            return $this->translator;
        }

        $driver = $driver ?? $this->getDefaultDriver();
        $loader = $this->driver($driver);

        // Wrap with cache if enabled
        $cache = $this->getCacheDriver();
        if ($this->shouldCache() && $cache !== null) {
            $loader = new CachedLoader(
                $loader,
                $cache,
                $this->config->get('translation.cache.ttl', 3600),
                $this->config->get('translation.cache.prefix', 'trans:')
            );
        }

        $translator = new Translator(
            $loader,
            new MessageSelector(),
            $this->config->get('translation.locale', 'en'),
            $this->config->get('translation.fallback', 'en')
        );

        // Add configured paths
        foreach ($this->getPaths() as $path) {
            $translator->addPath($path);
        }

        // Cache translator if using default driver
        if ($driver === null || $driver === $this->getDefaultDriver()) {
            $this->translator = $translator;
        }

        return $translator;
    }

    /**
     * Create the JSON driver.
     *
     * @return JsonLoader
     */
    public function createJsonDriver(): JsonLoader
    {
        $loader = new JsonLoader();

        foreach ($this->getPaths() as $path) {
            $loader->addPath($path);
        }

        return $loader;
    }

    /**
     * Create the Array (PHP) driver.
     *
     * @return ArrayLoader
     */
    public function createArrayDriver(): ArrayLoader
    {
        $loader = new ArrayLoader();

        foreach ($this->getPaths() as $path) {
            $loader->addPath($path);
        }

        return $loader;
    }

    /**
     * Create the Database driver.
     *
     * @return DatabaseLoader
     */
    public function createDatabaseDriver(): DatabaseLoader
    {
        $dbConfig = $this->config->get('translation.drivers.database', []);
        $connection = $this->database->getConnection($dbConfig['connection'] ?? null);

        return new DatabaseLoader(
            $connection,
            $dbConfig['table'] ?? 'translations'
        );
    }

    /**
     * Get configured translation paths.
     *
     * @return array<string>
     */
    protected function getPaths(): array
    {
        $paths = $this->config->get('translation.paths', []);

        if (empty($paths)) {
            // Default path
            $paths[] = base_path('lang');
        }

        return $paths;
    }

    /**
     * Determine if caching should be enabled.
     *
     * @return bool
     */
    protected function shouldCache(): bool
    {
        return $this->config->get('translation.cache.enabled', false);
    }

    /**
     * Get the available locales.
     *
     * @return array<string>
     */
    public function getAvailableLocales(): array
    {
        return $this->config->get('translation.available_locales', ['vi', 'en']);
    }

    /**
     * Flush all cached translations.
     *
     * @return void
     */
    public function flush(): void
    {
        if ($this->translator !== null) {
            $this->translator->flush();
        }

        $this->translator = null;
    }

    /**
     * Proxy calls to the default translator.
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $method, array $arguments = [])
    {
        return $this->translator()->$method(...$arguments);
    }
}
