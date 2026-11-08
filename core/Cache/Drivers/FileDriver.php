<?php

namespace Ryra\Core\Cache\Drivers;

use Ryra\Core\Cache\Exceptions\CacheException;
use Ryra\Core\Cache\Marshallers\MarshallerInterface;

class FileDriver extends AbstractDriver
{
    public function __construct(
        protected string $folder,
        MarshallerInterface $marshaller,
        ?string $prefix = null,
    ) {
        parent::__construct($marshaller, $prefix);

        if (!$this->ensureDirectoryExists($this->folder)) {
            throw new CacheException(
                \sprintf('Unable to create cache directory "%s". Please check permissions.', $folder)
            );
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);

        if (!\file_exists($file)) {
            return $default;
        }

        $handle = \fopen($file, 'r');
        if ($handle === false) {
            return $default;
        }

        try {
            // Shared lock cho read - chống race condition
            if (!\flock($handle, LOCK_SH)) {
                return $default;
            }

            $expire = (int) \trim(\fgets($handle));

            // Check TTL: 0 = forever, otherwise check expiry time
            if ($expire !== 0 && $expire < \time()) {
                \flock($handle, LOCK_UN);
                \fclose($handle);
                $this->deleteFile($file);
                return $default;
            }

            $data = \stream_get_contents($handle);
            \flock($handle, LOCK_UN);

            if ($data === false || $data === '') {
                return $default;
            }

            return $this->marshaller->unmarshall($data);
        } finally {
            if (\is_resource($handle)) {
                \fclose($handle);
            }
        }
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $file = $this->getFilePath($key);
        $dir = \dirname($file);

        if (!$this->ensureDirectoryExists($dir)) {
            return false;
        }

        $ttl = $this->ttlToInt($ttl);
        $expire = $ttl > 0 ? \time() + $ttl : 0;
        $data = $expire . "\n" . $this->marshaller->marshall($value);

        // Ghi vào temp file trước, sau đó rename (atomic operation)
        $tmp = $file . '.' . \uniqid('', true) . '.tmp';

        if (\file_put_contents($tmp, $data, LOCK_EX) === false) {
            return false;
        }

        // Rename là atomic trên cùng filesystem - chống race condition
        if (!\rename($tmp, $file)) {
            @\unlink($tmp);
            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        return $this->deleteFile($this->getFilePath($key));
    }

    public function clear(): bool
    {
        return $this->removeDirectory($this->folder, false);
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    protected function getFilePath(string $key): string
    {
        $hash = \sha1($this->getKey($key));
        $parts = \array_slice(\str_split($hash, 2), 0, 2);

        return $this->folder . '/' . \implode('/', $parts) . '/' . $hash;
    }

    protected function ensureDirectoryExists(string $dir): bool
    {
        if (\is_dir($dir)) {
            return true;
        }

        return @\mkdir($dir, 0777, true);
    }

    protected function deleteFile(string $file): bool
    {
        if (\file_exists($file)) {
            return @\unlink($file);
        }

        return true;
    }

    /**
     * Xóa thư mục cache (recursive).
     */
    protected function removeDirectory(string $dir, bool $removeRoot = true): bool
    {
        if (!\is_dir($dir)) {
            return true;
        }

        $items = new \FilesystemIterator($dir);

        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                $this->removeDirectory($item->getPathname(), true);
            } else {
                @\unlink($item->getPathname());
            }
        }

        if ($removeRoot) {
            @\rmdir($dir);
        }

        return true;
    }
}
