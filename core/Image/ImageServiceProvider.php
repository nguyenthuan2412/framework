<?php

namespace Ryra\Core\Image;

use Ryra\Core\Container\ServiceProvider;

class ImageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('image', function () {
            $driver = config('image.driver');
            return new ImageFactory(new $driver, config('image.options', []));
        });
    }

    public function boot(): void
    {
        foreach (config('image.styles') as $style => $modifiers) {
            $this->app->get('image')->addStyle($style, $modifiers);
        }
    }
}