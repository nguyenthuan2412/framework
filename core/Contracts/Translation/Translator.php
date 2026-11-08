<?php

namespace Ryra\Core\Contracts\Translation;

interface Translator
{
    /**
     * Get the translation for the given key.
     *
     * @param string $key The translation key (e.g., 'messages.welcome' or 'validation.required')
     * @param array $params Replacement parameters (if contains 'count', will auto-pluralize)
     * @param string|null $locale Override the current locale
     * @return string|array
     */
    public function get(string $key, array $params = [], ?string $locale = null): string|array;

    /**
     * Determine if a translation exists.
     *
     * @param string $key
     * @param string|null $locale
     * @return bool
     */
    public function has(string $key, ?string $locale = null): bool;

    /**
     * Get the current locale (from Context or default).
     *
     * @return string
     */
    public function getLocale(): string;

    /**
     * Set the locale for current coroutine context.
     *
     * @param string $locale
     * @return void
     */
    public function setLocale(string $locale): void;

    /**
     * Get the fallback locale.
     *
     * @return string
     */
    public function getFallback(): string;

    /**
     * Set the fallback locale.
     *
     * @param string $locale
     * @return void
     */
    public function setFallback(string $locale): void;

    /**
     * Add a path to search for translations.
     *
     * @param string $path
     * @param string|null $namespace
     * @return void
     */
    public function addPath(string $path, ?string $namespace = null): void;

    /**
     * Flush loaded translations cache.
     *
     * @return void
     */
    public function flush(): void;
}
