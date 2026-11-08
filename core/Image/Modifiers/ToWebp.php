<?php

namespace Ryra\Core\Image\Modifiers;

use Ryra\Core\Image\Image;

class ToWebp
{
    public function __construct(protected int $quality = 100, protected ?bool $strip = null) {}

    public function handle(Image $image, \Closure $next)
    {
        if (is_null($this->strip)) {
            $image->toWebp($this->quality);
        } else {
            $image->toWebp($this->quality, $this->strip);
        }
        return $next($image);
    }
}