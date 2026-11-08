<?php

namespace Ryra\Core\Twig\Extension;

use Twig\TwigFilter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Ryra\Core\Collection\Collection;

class CollectionExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('collect', [static::class, 'createCollection'])
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('collect', [static::class, 'createCollection'])
        ];
    }

    public static function createCollection(mixed $items = []): Collection
    {
        return new Collection($items);
    }
}