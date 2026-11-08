<?php

namespace Ryra\Core\Mail;

use Ryra\Core\Container\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('mailer_factory', fn ($app) => new MailerFactory($app->get('config')));
        $this->app->transient('mailer', fn ($app) => $app->get('mailer_factory')->driver());
        $this->app->alias('mailer', MailerInterface::class);
    }
}