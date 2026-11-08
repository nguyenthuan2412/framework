<?php

namespace Ryra\Core\Session;

use InvalidArgumentException;
use SessionHandlerInterface;
use Ryra\Core\App;

class SessionManager
{
    protected array $drivers = [];

    public function __construct(protected App $app) {}

    /**
     * Create an instance of the "null" session driver.
     */
    protected function createNullDriver(): Session
    {
        return $this->buildSession(new NullSessionHandler);
    }

    /**
     * Create an instance of the file session driver.
     */
    protected function createFileDriver(): Session
    {
        return $this->createNativeDriver();
    }

    /**
     * Create an instance of the file session driver.
     */
    protected function createNativeDriver(): Session
    {
        $lifetime = config('session.lifetime');

        return $this->buildSession(new FileSessionHandler(
            $this->app, config('session.files'), $lifetime
        ));
    }

    /**
     * Create an instance of the database session driver.
     */
    protected function createDatabaseDriver(): Session
    {
        $table = config('session.table');

        $lifetime = config('session.lifetime');

        return $this->buildSession(new DatabaseSessionHandler(
            $this->getDatabaseConnection(), $table, $lifetime, $this->app
        ));
    }

    /**
     * Get the database connection for the database driver.
     */
    protected function getDatabaseConnection()
    {
        $connection = config('session.connection');
        return $this->app->get('database')->getConnection($connection);
    }

    /**
     * Build the session instance.
     */
    protected function buildSession(SessionHandlerInterface $handler): Session
    {
        $cookie = $this->getSessionConfig()['cookie'];
        return new Session(
            $cookie,
            $handler,
            $this->app->get('request')->cookie->get($cookie, null),
            config('session.serialization', 'php')
        );
    }

    /**
     * Determine if requests for the same session should wait for each to finish before executing.
     */
    public function shouldBlock()
    {
        return config('session.block', false);
    }

    /**
     * Get the name of the cache store / driver that should be used to acquire session locks.
     */
    public function blockDriver(): ?string
    {
        return config('session.block_store');
    }

    /**
     * Get the maximum number of seconds the session lock should be held for.
     */
    public function defaultRouteBlockLockSeconds(): int
    {
        return config('session.block_lock_seconds', 10);
    }

    /**
     * Get the maximum number of seconds to wait while attempting to acquire a route block session lock.
     */
    public function defaultRouteBlockWaitSeconds(): int
    {
        return config('session.block_wait_seconds', 10);
    }

    /**
     * Get the session configuration.
     */
    public function getSessionConfig(): array
    {
        return config('session');
    }

    /**
     * Get the default session driver name.
     */
    public function getDefaultDriver(): ?string
    {
        return config('session.driver');
    }

    public function driver(?string $driver = null): Session
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (is_null($driver)) {
            throw new InvalidArgumentException(sprintf(
                'Unable to resolve NULL driver for [%s].', static::class
            ));
        }

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }
        return $this->drivers[$driver];
    }

    protected function createDriver($driver): Session
    {
        $method = 'create'.ucfirst($driver).'Driver';
        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new InvalidArgumentException("Driver [$driver] not supported.");
    }

    /**
     * Set the default session driver name.
     */
    public function setDefaultDriver(string $name): void
    {
        config('session.driver', $name);
    }
}