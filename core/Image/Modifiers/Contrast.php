<?php

namespace Ryra\Core\Image\Modifiers;

use Ryra\Core\Image\Image;

class Contrast
{
    public function __construct(protected int $level) {}

    public function handle(Image $image, \Closure $next)
    {
        $image->contrast($this->level);
        return $next($image);
    }
}