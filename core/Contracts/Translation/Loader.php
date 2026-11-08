<?php

namespace Ryra\Core\Contracts\Translation;

interface Loader
{
    /**
     * Load the messages for the given locale and namespace.
     *
     * @param string $locale The locale to load (e.g., 'vi', 'en')
     * @param string $namespace The translation namespace (e.g., 'validation', 'messages')
     * @return array<string, mixed> The loaded translations
     */
    public function load(string $locale, string $namespace): array;

    /**
     * Get all available namespaces for a locale.
     *
     * @param string $locale
     * @return array<string>
     */
    public function namespaces(string $locale): array;

    /**
     * Add a path to search for translations.
     *
     * @param string $path
     * @param string|null $namespace Optional namespace prefix
     * @return void
     */
    public function addPath(string $path, ?string $namespace = null): void;
}
