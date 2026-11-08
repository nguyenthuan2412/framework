<?php

namespace Ryra\Core\Image\Modifiers;

use Ryra\Core\Image\Image;

class Flip
{
    public function handle(Image $image, \Closure $next)
    {
        $image->flip();
        return $next($image);
    }
}