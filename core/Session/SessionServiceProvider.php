<?php

namespace Ryra\Core\Session;

use Ryra\Core\Container\ServiceProvider;
use Ryra\Core\Contracts\Session\SessionHandler;

class SessionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped('session.manager', fn ($app) => new SessionManager($app));
        $this->app->alias('session.manager', SessionManager::class);

        $this->app->scoped('session', fn ($app) => $app->get('session.manager')->driver());
        $this->app->alias('session', SessionHandler::class);
    }
}