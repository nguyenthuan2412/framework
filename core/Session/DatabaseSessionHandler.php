<?php

namespace Ryra\Core\Session;

use Ryra\Core\Database\Query\QueryException;
use Ryra\Core\Database\Query\Builder;
use Ryra\Core\Database\Connection;
use Ryra\Core\Date\Date;
use Ryra\Core\App;

class DatabaseSessionHandler implements \Ryra\Core\Contracts\Session\SessionHandler
{
    protected bool $exists = false;

    public function __construct(
        protected Connection $connection,
        protected string $table,
        protected int $minutes,
        protected App $app
    ) {}

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $sessionId): string|false
    {
        $session = $this->getQuery()->find($sessionId);
        if (is_null($session)) {
            return '';
        }

        if ($this->expired($session)) {
            $this->exists = true;

            return '';
        }

        if (isset($session['payload'])) {
            $this->exists = true;
            return $session['payload'];
        }

        return '';
    }

    /**
     * Determine if the session is expired.
     */
    protected function expired(array $session): bool
    {
        return isset($session['last_activity']) &&
            $session['last_activity'] < Date::now()->subMinutes($this->minutes)->getTimestamp();
    }

    public function write(string $sessionId, string $data): bool
    {
        $payload = $this->getDefaultPayload($data);

        if (!$this->exists) {
            $this->read($sessionId);
        }

        if ($this->exists) {
            $this->performUpdate($sessionId, $payload);
        } else {
            $this->performInsert($sessionId, $payload);
        }

        return $this->exists = true;
    }

    /**
     * Perform an insert operation on the session ID.
     */
    protected function performInsert($sessionId, $payload)
    {
        try {
            $payload['id'] = $sessionId;
            return $this->getQuery()->insert($payload);
        } catch (QueryException $e) {
            $this->performUpdate($sessionId, $payload);
        }
    }

    /**
     * Perform an update operation on the session ID.
     */
    protected function performUpdate(string $sessionId, array $payload): int
    {
        return $this->getQuery()->where('id', $sessionId)->update($payload);
    }

    /**
     * Get the default payload for the session.
     */
    protected function getDefaultPayload($data): array
    {
        return [
            'payload' => $data,
            'last_activity' => $this->currentTime(),
            'ip_address' => $this->ipAddress(),
            'user_agent' => $this->userAgent(),
        ];
    }

    /**
     * Get the current system time as a UNIX timestamp.
     */
    protected function currentTime(): int
    {
        return Date::now()->getTimestamp();
    }

    public function destroy(string $sessionId): bool
    {
        $this->getQuery()->where('id', $sessionId)->delete();
        return true;
    }

    public function gc(int $lifetime): int
    {
        return $this->getQuery()
            ->where('last_activity', $this->currentTime() - $lifetime, '<=')->delete();
    }

    /**
     * Get the IP address for the current request.
     */
    protected function ipAddress(): string
    {
        return $this->app->get('request')->ip();
    }

    /**
     * Get the user agent for the current request.
     */
    protected function userAgent(): string
    {
        return substr((string) $this->app->get('request')->header('User-Agent'), 0, 500);
    }

    protected function getQuery(): Builder
    {
        return $this->connection->query()->from($this->table);
    }
}