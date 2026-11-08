<?php

namespace Ryra\Core\Translation;

use Ryra\Core\Container\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider
{
    /**
     * Register translation services.
     *
     * @return void
     */
    public function register(): void
    {
        // Register the translation manager
        $this->app->singleton('translation', fn ($app) => new TranslationManager($app));

        // Register translator alias for convenience
        $this->app->singleton('translator', fn ($app) => $app->get('translation')->translator());

        // Register interface aliases
        $this->app->alias('translator', \Ryra\Core\Contracts\Translation\Translator::class);
        $this->app->alias('translation', TranslationManager::class);
    }
}
