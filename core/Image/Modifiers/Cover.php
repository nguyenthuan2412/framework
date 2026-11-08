<?php

namespace Ryra\Core\Image\Modifiers;

use Ryra\Core\Image\Image;

class Cover
{
    public function __construct(protected float $width, protected float $height) {}

    public function handle(Image $image, \Closure $next)
    {
        $image->cover($this->width, $this->height);
        return $next($image);
    }
}