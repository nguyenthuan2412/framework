<?php

namespace Ryra\Core\Image\Modifiers;

use Ryra\Core\Image\Image;

class Gamma
{
    public function __construct(protected float $level) {}

    public function handle(Image $image, \Closure $next)
    {
        $image->gamma($this->level);
        return $next($image);
    }
}