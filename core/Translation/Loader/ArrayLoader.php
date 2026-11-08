<?php

namespace Ryra\Core\Translation\Loader;

use Ryra\Core\Translation\Exceptions\TranslationException;

class ArrayLoader extends AbstractLoader
{
    /**
     * File extension for PHP array translation files.
     */
    protected string $extension = '.php';

    /**
     * {@inheritdoc}
     */
    public function load(string $locale, string $namespace): array
    {
        // Return cached if available
        if ($cached = $this->getCached($locale, $namespace)) {
            return $cached;
        }

        $translations = [];

        foreach ($this->getPathsForNamespace($namespace) as $path) {
            $filePath = $this->buildFilePath($path, $locale, $namespace);

            if (file_exists($filePath)) {
                $translations = array_replace_recursive(
                    $translations,
                    $this->readFile($filePath)
                );
            }
        }

        return $this->cacheTranslations($locale, $namespace, $translations);
    }

    /**
     * {@inheritdoc}
     */
    protected function readFile(string $filePath): array
    {
        $data = require $filePath;

        if (!is_array($data)) {
            throw TranslationException::invalidFile(
                $filePath,
                'PHP translation file must return an array'
            );
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    protected function discoverNamespaces(string $path, string $locale): array
    {
        $localePath = $path . DIRECTORY_SEPARATOR . $locale;

        if (!is_dir($localePath)) {
            return [];
        }

        $namespaces = [];
        $pattern = $localePath . DIRECTORY_SEPARATOR . '*' . $this->extension;

        foreach (glob($pattern) as $file) {
            $namespaces[] = basename($file, $this->extension);
        }

        return $namespaces;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildFilePath(string $path, string $locale, string $namespace): string
    {
        return sprintf(
            '%s%s%s%s%s%s',
            $path,
            DIRECTORY_SEPARATOR,
            $locale,
            DIRECTORY_SEPARATOR,
            $namespace,
            $this->extension
        );
    }
}
