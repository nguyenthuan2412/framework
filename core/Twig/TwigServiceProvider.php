<?php

namespace Ryra\Core\Twig;

use Ryra\Core\Container\ServiceProvider;
use Ryra\Core\Twig\Extension\AssetExtension;
use Ryra\Core\Twig\Extension\CollectionExtension;
use Ryra\Core\Twig\Extension\CsrfExtension;
use Ryra\Core\Twig\Extension\OldInputExtension;
use Ryra\Core\Twig\Extension\RouteExtension;
use Ryra\Core\Twig\Extension\StorageExtension;
use Ryra\Core\Twig\Extension\TranslateExtension;

class TwigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('twig.config', fn() => new TwigConfig(config('twig')));
        $this->app->singleton('twig.loader', fn() => new TwigLoader());
        $this->app->scoped(
            'twig.environment',
            function ($app) {
                $twig = new TwigEnvironment($app->get('twig.loader'), config('twig.options'));

                $twig->addExtension($app->make(OldInputExtension::class));
                $twig->addExtension($app->make(CsrfExtension::class));
                $twig->addExtension($app->make(CollectionExtension::class));
                $twig->addExtension($app->make(RouteExtension::class));
                $twig->addExtension($app->make(AssetExtension::class));
                $twig->addExtension($app->make(StorageExtension::class));
                $twig->addExtension($app->make(TranslateExtension::class));

                foreach ((array) config('twig.extensions', []) as $extension) {
                    $twig->addExtension(\is_string($extension) ? $app->make($extension) : $extension);
                }

                return $twig;
            }
        );
        $this->app->alias('twig.environment', TwigEnvironment::class);
    }

    public function boot(): void
    {
        $modules = $this->app->get('module.manager');
        $modules->addAfterInstall(function ($module) {
            $loader = $this->app->get('twig.loader');
            if (is_dir($dirView = join_paths($module->getPath(), 'views'))) {
                $loader->addPath($dirView, $module->getId());
            }
        });
    }
}