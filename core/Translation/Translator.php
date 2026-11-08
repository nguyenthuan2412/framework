<?php

namespace Ryra\Core\Translation;

use Ryra\Core\Translation\Exceptions\TranslationException;
use Ryra\Core\Contracts\Translation\MessageSelector;
use Ryra\Core\Contracts\Translation\Loader;
use Ryra\Core\Context\Context;

class Translator implements \Ryra\Core\Contracts\Translation\Translator
{
    /**
     * Default fallback locale.
     */
    protected string $fallback;

    /**
     * Default locale (used when Context has no locale).
     */
    protected string $defaultLocale;

    /**
     * Loaded translations cache.
     * Structure: [locale][namespace] => translations array
     *
     * @var array<string, array<string, array>>
     */
    protected array $loaded = [];

    /**
     * Key separator for nested keys.
     */
    protected string $keySeparator = '.';

    /**
     * Create a new translator instance.
     */
    public function __construct(
        protected Loader $loader,
        protected MessageSelector $selector,
        string $defaultLocale = 'vi',
        string $fallback = 'en'
    ) {
        $this->defaultLocale = $defaultLocale;
        $this->fallback = $fallback;
    }

    /**
     * {@inheritdoc}
     *
     * Key format: "namespace.key.subkey" or just "key" (uses 'messages' namespace)
     *
     * If params contains 'count' and translation contains '|', auto-pluralize.
     */
    public function get(string $key, array $params = [], ?string $locale = null): string|array
    {
        $locale = $locale ?? $this->getLocale();

        // Parse key into namespace and item key
        [$namespace, $itemKey] = $this->parseKey($key);

        // Try to get translation for current locale
        $translation = $this->getTranslation($namespace, $itemKey, $locale);

        // Fallback to fallback locale if not found
        if ($translation === null && $locale !== $this->fallback) {
            $translation = $this->getTranslation($namespace, $itemKey, $this->fallback);
        }

        // Return key if translation not found
        if ($translation === null) {
            return $key;
        }

        // If translation is array, return as-is (for nested translations)
        if (is_array($translation)) {
            return $translation;
        }

        // Auto-pluralize if params has 'count' and translation has '|'
        if (isset($params['count']) && str_contains($translation, '|')) {
            $count = $params['count'];

            if (is_array($count) || $count instanceof \Countable) {
                $count = count($count);
            }

            $translation = $this->selector->choose($translation, $count, $locale);
        }

        // Replace parameters
        return $this->replaceParameters($translation, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key, ?string $locale = null): bool
    {
        $translation = $this->get($key, [], $locale);
        return $translation !== $key;
    }

    /**
     * {@inheritdoc}
     *
     * Gets locale from App (per coroutine via Context).
     */
    public function getLocale(): string
    {
        return Context::get('__locale', \config('translation.locale', 'vi'));
    }

    /**
     * {@inheritdoc}
     *
     * Sets locale via App (per coroutine via Context).
     *
     * @throws TranslationException If locale is invalid.
     */
    public function setLocale(string $locale): void
    {
        $this->validateLocale($locale);
        Context::put('__locale', $locale);
    }

    /**
     * {@inheritdoc}
     */
    public function getFallback(): string
    {
        return $this->fallback;
    }

    /**
     * {@inheritdoc}
     *
     * @throws TranslationException If locale is invalid.
     */
    public function setFallback(string $locale): void
    {
        $this->validateLocale($locale);
        $this->fallback = $locale;
    }

    /**
     * Get the default locale.
     *
     * @return string
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Set the default locale.
     *
     * @param string $locale
     * @return void
     * @throws TranslationException If locale is invalid.
     */
    public function setDefaultLocale(string $locale): void
    {
        $this->validateLocale($locale);
        $this->defaultLocale = $locale;
    }

    /**
     * {@inheritdoc}
     */
    public function addPath(string $path, ?string $namespace = null): void
    {
        $this->loader->addPath($path, $namespace);
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): void
    {
        $this->loaded = [];
    }

    /**
     * Get the loader instance.
     *
     * @return Loader
     */
    public function getLoader(): Loader
    {
        return $this->loader;
    }

    /**
     * Get the message selector instance.
     */
    public function getSelector(): MessageSelector
    {
        return $this->selector;
    }

    /**
     * Get available locales from config.
     *
     * @return array<string>
     */
    public function getAvailableLocales(): array
    {
        return \config('translation.available_locales', ['vi', 'en']);
    }

    /**
     * Check if a locale is valid.
     *
     * @param string $locale
     * @return bool
     */
    public function isValidLocale(string $locale): bool
    {
        return in_array($locale, $this->getAvailableLocales(), true);
    }

    /**
     * Validate locale and throw exception if invalid.
     *
     * @param string $locale
     * @return void
     * @throws TranslationException If locale is invalid.
     */
    protected function validateLocale(string $locale): void
    {
        if (!$this->isValidLocale($locale)) {
            throw TranslationException::invalidLocale($locale);
        }
    }

    /**
     * Parse the key into namespace and item key.
     *
     * @param string $key
     * @return array{0: string, 1: string} [namespace, itemKey]
     */
    protected function parseKey(string $key): array
    {
        // Check if key contains vendor namespace separator (vendor::namespace.key)
        if (str_contains($key, '::')) {
            [$namespace, $itemKey] = explode('::', $key, 2);
            return [$namespace, $itemKey];
        }

        // Check if key contains dot (namespace.key format)
        $firstDotPos = strpos($key, $this->keySeparator);

        if ($firstDotPos !== false) {
            $namespace = substr($key, 0, $firstDotPos);
            $itemKey = substr($key, $firstDotPos + 1);
            return [$namespace, $itemKey];
        }

        // No namespace, use default 'messages'
        return ['messages', $key];
    }

    /**
     * Get the translation value from loaded translations.
     */
    protected function getTranslation(string $namespace, string $key, string $locale): mixed
    {
        // Load namespace if not loaded
        $this->loadNamespace($namespace, $locale);

        // Get translations for this namespace
        $translations = $this->loaded[$locale][$namespace] ?? [];

        // Navigate to the key (supports nested keys like "errors.validation.required")
        return $this->getNestedValue($translations, $key);
    }

    /**
     * Load a namespace for the given locale.
     */
    protected function loadNamespace(string $namespace, string $locale): void
    {
        if (isset($this->loaded[$locale][$namespace])) {
            return;
        }

        if (!isset($this->loaded[$locale])) {
            $this->loaded[$locale] = [];
        }

        $this->loaded[$locale][$namespace] = $this->loader->load($locale, $namespace);
    }

    /**
     * Get nested value using dot notation.
     */
    protected function getNestedValue(array $array, string $key): mixed
    {
        // If key exists directly, return it
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        // Try nested access with dot notation
        $keys = explode($this->keySeparator, $key);
        $value = $array;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Replace parameters in the translation string.
     *
     * Supports both :param and {param} style placeholders.
     */
    protected function replaceParameters(string $translation, array $params): string
    {
        if (empty($params)) {
            return $translation;
        }

        $replacements = [];

        foreach ($params as $key => $value) {
            // Skip non-scalar values
            if (!is_scalar($value) && !is_null($value)) {
                if (is_array($value) || $value instanceof \Countable) {
                    $value = count($value);
                } else {
                    continue;
                }
            }

            $value = (string) $value;

            // Support both :key and key formats
            $cleanKey = ltrim($key, ':');

            // :key format (Laravel style)
            $replacements[':' . $cleanKey] = $value;
            $replacements[':' . strtoupper($cleanKey)] = strtoupper($value);
            $replacements[':' . ucfirst($cleanKey)] = ucfirst($value);

            // {key} format (common in other frameworks)
            $replacements['{' . $cleanKey . '}'] = $value;
        }

        return strtr($translation, $replacements);
    }
}
