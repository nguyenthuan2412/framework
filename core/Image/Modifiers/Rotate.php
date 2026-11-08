<?php

namespace Ryra\Core\Image\Modifiers;

use Ryra\Core\Image\Image;

class Rotate
{
    public function __construct(protected float $angle, protected mixed $background = 'ffffff') {}

    public function handle(Image $image, \Closure $next)
    {
        $image->rotate($this->angle, $this->background);
        return $next($image);
    }
}