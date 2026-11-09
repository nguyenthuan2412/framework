<?php

namespace Ryra\Core\Container\Proxy;

/**
 * Quyết định tên class / đường dẫn file proxy cho một class.
 *
 * Tên file gồm 2 phần hash:
 *  - hash tên class: để tìm và xoá các proxy cũ của cùng class.
 *  - hash phiên bản: đổi khi file của class, class cha hoặc trait bị sửa (theo filemtime),
 *    hoặc khi ProxyGenerator::VERSION đổi. Nhờ vậy sửa code là tự sinh proxy mới.
 */
class ProxyFileLocator
{
    public const NAMESPACE = 'Ryra\\Runtime\\Proxy';

    public function __construct(private string $proxyFolder) {}

    public function getProxyFolder(): string
    {
        return $this->proxyFolder;
    }

    public function getProxyClassName(\ReflectionClass $class): string
    {
        return 'P_' . $this->classHash($class->getName()) . '_' . $this->versionHash($class);
    }

    public function getProxyFile(string $proxyClassName): string
    {
        return $this->proxyFolder . DIRECTORY_SEPARATOR . $proxyClassName . '.php';
    }

    /**
     * Pattern glob của mọi proxy (mọi phiên bản) thuộc class này.
     */
    public function getProxyFilePattern(string $class): string
    {
        return $this->proxyFolder . DIRECTORY_SEPARATOR . 'P_' . $this->classHash($class) . '_*.php';
    }

    private function classHash(string $class): string
    {
        return \substr(\hash('xxh128', $class), 0, 16);
    }

    private function versionHash(\ReflectionClass $class): string
    {
        $parts = [ProxyGenerator::VERSION];
        foreach ($this->sourceFiles($class) as $file) {
            $parts[] = $file . ':' . (@\filemtime($file) ?: 0);
        }

        return \substr(\hash('xxh128', \implode('|', $parts)), 0, 16);
    }

    /**
     * File nguồn của class, các class cha và mọi trait (kể cả trait lồng nhau).
     *
     * @return string[]
     */
    private function sourceFiles(\ReflectionClass $class): array
    {
        $files = [];
        $stack = [$class];
        while ($stack) {
            $current = \array_pop($stack);
            if ($current->getFileName()) {
                $files[$current->getFileName()] = true;
            }
            foreach ($current->getTraits() as $trait) {
                $stack[] = $trait;
            }
            if ($parent = $current->getParentClass()) {
                $stack[] = $parent;
            }
        }

        return \array_keys($files);
    }
}
