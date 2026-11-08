<?php

use Ryra\Core\App;
use Ryra\Core\Translation\Translator;

if (!function_exists('t')) {
    /**
     * Get the translation for the given key.
     *
     * If params contains 'count' and translation contains '|', auto-pluralize.
     *
     * @param string|null $key The translation key (e.g., 'messages.welcome')
     * @param array $params Replacement parameters (use 'count' for auto-pluralization)
     * @param string|null $locale Override the current locale
     * @return string|array|Translator Returns Translator if key is null
     *
     * @example
     * // Basic translation
     * t('messages.welcome', ['name' => 'John'])
     *
     * // Auto-pluralization (translation: "{0} No items|{1} One item|[2,*] :count items")
     * t('messages.items', ['count' => 5])
     *
     * // Override locale
     * t('messages.welcome', ['name' => 'John'], 'en')
     */
    function t(?string $key = null, array $params = [], ?string $locale = null): string|array|Translator
    {
        /** @var Translator $translator */
        $translator = App::getInstance()->get('translator');

        if ($key === null) {
            return $translator;
        }

        return $translator->get($key, $params, $locale);
    }
}

if (!function_exists('translator')) {
    /**
     * Get the translator instance.
     *
     * @return Translator
     */
    function translator(): Translator
    {
        return App::getInstance()->get('translator');
    }
}
