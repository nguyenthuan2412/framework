<?php

namespace Ryra\Core\Image\Modifiers;

use Ryra\Core\Image\Image;

class Brightness
{
    public function __construct(protected int $level) {}

    public function handle(Image $image, \Closure $next)
    {
        $image->brightness($this->level);
        return $next($image);
    }
}