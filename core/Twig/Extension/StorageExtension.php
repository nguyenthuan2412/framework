<?php

namespace Ryra\Core\Twig\Extension;

use Twig\TwigFilter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class StorageExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('storage_path', 'storage_path'),
            new TwigFunction('public_path', 'public_path'),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('storage_path', 'storage_path'),
            new TwigFilter('public_path', 'public_path'),
        ];
    }
}