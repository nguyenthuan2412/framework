<?php

namespace Ryra\Core\Image\Modifiers;

use Ryra\Core\Image\Image;

class Flop
{
    public function handle(Image $image, \Closure $next)
    {
        $image->flop();
        return $next($image);
    }
}