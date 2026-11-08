<?php

namespace Ryra\Core\Twig\Extension;

use Twig\TwigFilter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TranslateExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('t', 't'),
            new TwigFunction('t', 't'),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('t', 't'),
            new TwigFilter('t', 't'),
        ];
    }
}