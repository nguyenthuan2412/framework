<?php

namespace Ryra\Core\Database;

use Ryra\Core\Container\ServiceProvider;
use Ryra\Core\Database\Model\Model;
use Ryra\Core\Database\ConnectionManager;
use Ryra\Core\Pool\PoolOption;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('database', fn ($app) => new ConnectionManager($app->get('pool')));
        $this->app->alias('database', \Ryra\Core\Contracts\Database\ConnectionManager::class);
    }

    public function boot(): void
    {
        Model::setEventDispatcher($this->app->get('events'));

        $database = $this->app->get('database');
        foreach (\config('database.connections') as $connection => $config) {
            if (isset($config['pool_options'])) {
                $config['pool_options'] = PoolOption::fromArray($config['pool_options']);
            }
            $database->addConnection($connection, $config);
        }
        $database->setDefaultConnection(\config('database.default'));
    }
}