<?php

namespace Ryra\Core\Image\Modifiers;

use Ryra\Core\Image\Image;

class Greyscale
{
    public function handle(Image $image, \Closure $next)
    {
        $image->greyscale();
        return $next($image);
    }
}