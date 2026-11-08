<?php

namespace Ryra\Core\Twig\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static string render(string $name, array $context = [])
 */
class Twig extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'twig.environment';
    }
}