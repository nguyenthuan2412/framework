<?php

namespace Ryra\Core\Twig\Extension;

use Twig\TwigFilter;
use Ryra\Core\Session\Facade\Session;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CsrfExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('csrf_token', [static::class, 'generateCsrfToken'], ['is_safe' => ['html']])
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('csrf_token', [static::class, 'generateCsrfToken'], ['is_safe' => ['html']])
        ];
    }

    public static function generateCsrfToken(?string $name = null): string
    {
        $token = Session::regenerateToken();
        return "<input type='hidden' name='_token' value='$token' />";
    }
}