<?php

namespace Ryra\Core\Translation\Exceptions;

use RuntimeException;

class TranslationException extends RuntimeException
{
    /**
     * Create exception for invalid translation file.
     *
     * @param string $filePath
     * @param string $reason
     * @return static
     */
    public static function invalidFile(string $filePath, string $reason = ''): static
    {
        $message = sprintf('Invalid translation file: %s', $filePath);

        if ($reason) {
            $message .= ' - ' . $reason;
        }

        return new static($message);
    }

    /**
     * Create exception for file not found.
     *
     * @param string $filePath
     * @return static
     */
    public static function fileNotFound(string $filePath): static
    {
        return new static(sprintf('Translation file not found: %s', $filePath));
    }

    /**
     * Create exception for invalid JSON.
     *
     * @param string $filePath
     * @param string $error
     * @return static
     */
    public static function invalidJson(string $filePath, string $error): static
    {
        return new static(sprintf('Invalid JSON in translation file %s: %s', $filePath, $error));
    }

    /**
     * Create exception for invalid locale.
     *
     * @param string $locale
     * @return static
     */
    public static function invalidLocale(string $locale): static
    {
        return new static(sprintf('Invalid locale: %s', $locale));
    }
}
