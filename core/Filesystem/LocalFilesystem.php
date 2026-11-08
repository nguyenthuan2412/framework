<?php

namespace Ryra\Core\Filesystem;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

class LocalFilesystem extends Filesystem implements FilesystemInterface
{
    private string $rootLocation;

    public function __construct(array $configs)
    {
        $adapter = new LocalFilesystemAdapter(
            location: $configs['root'],
            visibility: $configs['visibility'],
        );

        parent::__construct($adapter, ['public_url' => $configs['url']]);
        $this->rootLocation = $configs['root'];
    }

    public function path(?string $path = null): string
    {
        return is_null($path) ? $this->rootLocation : join_paths($this->rootLocation, $path);
    }
}