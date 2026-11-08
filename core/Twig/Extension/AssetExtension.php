<?php

namespace Ryra\Core\Twig\Extension;

use Twig\TwigFilter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AssetExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset', 'asset')
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('asset', 'asset')
        ];
    }
}