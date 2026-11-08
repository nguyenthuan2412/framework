<?php

namespace Ryra\Core\Twig\Extension;

use Twig\TwigFilter;
use Ryra\Core\Session\Facade\Session;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class OldInputExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('old', [static::class, 'getOldInput'])
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('old', [static::class, 'getOldInput'])
        ];
    }

    public static function getOldInput(string $name): mixed
    {
        return Session::getOldInput($name);
    }
}