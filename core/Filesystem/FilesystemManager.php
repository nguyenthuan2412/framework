<?php

namespace Ryra\Core\Filesystem;

use Ryra\Core\Contracts\Config\Repository;

class FilesystemManager
{
    public function __construct(protected Repository $config)
    {
    }

    public function getDefaultDisk(): string
    {
        return $this->config->get('filesystems.default', 'local');
    }

    public function getFilesystemByConfig(array $config): FilesystemInterface
    {
        return match ($config['driver']) {
            'local' => new LocalFilesystem($config),
            's3' => new S3Filesystem($config),
        };
    }

    public function disk(?string $driver = null): FilesystemInterface
    {
        $driver ??= $this->getDefaultDisk();
        $config = $this->config->get("filesystems.disks.$driver");
        return $this->getFilesystemByConfig($config);
    }

    public function __call(string $name, array $arguments): mixed
    {
        return $this->disk()->$name(...$arguments);
    }
}