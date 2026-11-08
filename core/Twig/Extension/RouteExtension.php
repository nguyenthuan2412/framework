<?php

namespace Ryra\Core\Twig\Extension;

use Twig\TwigFilter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RouteExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('route', 'route')
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('route', 'route')
        ];
    }
}