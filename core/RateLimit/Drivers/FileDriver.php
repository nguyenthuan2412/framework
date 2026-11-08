<?php

namespace Ryra\Core\RateLimit\Drivers;

use Ryra\Core\RateLimit\Contracts\RateLimiterDriverInterface;

/**
 * File driver — no external dependencies.
 * Best for: small/medium traffic, single-server, dev environments.
 * Uses exclusive file locks to prevent race conditions.
 */
class FileDriver implements RateLimiterDriverInterface
{
    public function __construct(
        private string $folder,
        private string $prefix = 'rl:',
    ) {
        if (!is_dir($folder)) {
            @mkdir($folder, 0777, true);
        }
    }

    private function filePath(string $key): string
    {
        $hash  = sha1($this->prefix . $key);
        $parts = array_slice(str_split($hash, 2), 0, 2);
        $dir   = $this->folder . '/' . implode('/', $parts);

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir . '/' . $hash . '.rl';
    }

    private function readRaw(string $key): ?array
    {
        $file = $this->filePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $handle = fopen($file, 'r');

        if ($handle === false) {
            return null;
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                return null;
            }

            $content = stream_get_contents($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if (empty($content)) {
            return null;
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            return null;
        }

        // Expired
        if (isset($data['expires_at']) && $data['expires_at'] > 0 && $data['expires_at'] <= time()) {
            @unlink($file);
            return null;
        }

        return $data;
    }

    private function writeRaw(string $key, array $data): bool
    {
        $file = $this->filePath($key);
        $tmp  = $file . '.' . uniqid('', true) . '.tmp';

        if (file_put_contents($tmp, json_encode($data), LOCK_EX) === false) {
            return false;
        }

        // rename is atomic on same filesystem
        if (!rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * Uses exclusive lock for atomicity.
     */
    public function increment(string $key, int $ttl): int
    {
        $file   = $this->filePath($key);
        $handle = fopen($file, 'c+');

        if ($handle === false) {
            return 1;
        }

        try {
            flock($handle, LOCK_EX);

            $content = stream_get_contents($handle);
            rewind($handle);

            $data = null;

            if (!empty($content)) {
                $decoded = json_decode($content, true);

                if (is_array($decoded) && isset($decoded['expires_at'])) {
                    $notExpired = $decoded['expires_at'] === 0 || $decoded['expires_at'] > time();

                    if ($notExpired) {
                        $data = $decoded;
                    }
                }
            }

            if ($data === null) {
                $data = ['count' => 1, 'expires_at' => time() + $ttl];
            } else {
                $data['count'] = ($data['count'] ?? 0) + 1;
            }

            ftruncate($handle, 0);
            fwrite($handle, json_encode($data));
            flock($handle, LOCK_UN);

            return $data['count'];
        } finally {
            fclose($handle);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getData(string $key): ?array
    {
        $data = $this->readRaw($key);

        if ($data === null) {
            return null;
        }

        return $data['payload'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function setData(string $key, array $data, int $ttl): bool
    {
        return $this->writeRaw($key, [
            'payload'    => $data,
            'expires_at' => time() + $ttl,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function reset(string $key): bool
    {
        $file = $this->filePath($key);

        if (file_exists($file)) {
            return (bool) @unlink($file);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function ttl(string $key): int
    {
        $data = $this->readRaw($key);

        if ($data === null) {
            return -2;
        }

        if (!isset($data['expires_at']) || $data['expires_at'] === 0) {
            return -1;
        }

        $remaining = $data['expires_at'] - time();

        return $remaining > 0 ? $remaining : -2;
    }
}
