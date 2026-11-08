<?php

namespace Ryra\Core\Image\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static void addStyle(string $style, array $modifiers)
 * @method static array getModifiers(string $style)
 * @method static ?\Ryra\Core\Image\Image read(string $path)
 * @method static ?\Ryra\Core\Image\Image readFromStorage(string $path)
 */
class Image extends Facade
{
    protected static function getFacadeAccessor(): string|object
    {
        return 'image';
    }
}