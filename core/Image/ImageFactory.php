<?php

namespace Ryra\Core\Image;

use \Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;

class ImageFactory
{
    protected array $styles = [];

    public readonly ImageManager $manager;

    public function __construct(string|DriverInterface $driver, mixed ...$options)
    {
        $this->manager = new ImageManager(new $driver, ...$options);
    }

    public function addStyle(string $style, array $modifiers): void
    {
        $this->styles[$style] = $modifiers;
    }

    public function getModifiers(string $style): array
    {
        return $this->styles[$style];
    }

    public function readFromStorage(string $path): ?Image
    {
        return $this->read(storage_path($path));
    }

    public function read(string $path): ?Image
    {
        if (file_exists($path)) {
            return new Image($path, $this);
        }

        return null;
    }
}