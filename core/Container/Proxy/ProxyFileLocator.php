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

    /**
     * @param string $prefix Phân biệt loại proxy (P = factory, A = AOP).
     * @param string $extra  Dữ liệu thêm vào hash phiên bản (vd: cấu hình aspect).
     */
    public function getProxyClassName(\ReflectionClass $class, string $prefix = 'P', string $extra = ''): string
    {
        return $prefix . '_' . $this->classHash($class->getName()) . '_' . $this->versionHash($class, $extra);
    }

    public function getProxyFile(string $proxyClassName): string
    {
        return $this->proxyFolder . DIRECTORY_SEPARATOR . $proxyClassName . '.php';
    }

    /**
     * Pattern glob của mọi proxy (mọi phiên bản) thuộc class này.
     */
    public function getProxyFilePattern(string $class, string $prefix = 'P'): string
    {
        return $this->proxyFolder . DIRECTORY_SEPARATOR . $prefix . '_' . $this->classHash($class) . '_*.php';
    }

    /**
     * Ghi file proxy: xoá các phiên bản cũ khớp $oldPattern, ghi ra file tạm rồi rename
     * để nhiều worker cùng sinh một proxy cũng không đọc phải file ghi dở.
     */
    public function write(string $file, string $code, string $oldPattern): bool
    {
        $folder = $this->proxyFolder;
        if (!\is_dir($folder) && !@\mkdir($folder, 0755, true) && !\is_dir($folder)) {
            return false;
        }

        foreach (\glob($oldPattern) ?: [] as $old) {
            if ($old !== $file) {
                @\unlink($old);
            }
        }

        $tmp = $file . '.' . \getmypid() . '.' . \bin2hex(\random_bytes(4)) . '.tmp';
        if (@\file_put_contents($tmp, $code) === false) {
            return false;
        }
        if (!@\rename($tmp, $file)) {
            @\unlink($tmp);
            return \is_file($file);
        }

        return true;
    }

    private function classHash(string $class): string
    {
        return \substr(\hash('xxh128', $class), 0, 16);
    }

    private function versionHash(\ReflectionClass $class, string $extra = ''): string
    {
        $parts = [ProxyGenerator::VERSION, $extra];
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
