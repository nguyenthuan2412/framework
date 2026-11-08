<?php

namespace Ryra\Core\Session;

use Symfony\Component\Finder\Finder;
use Ryra\Core\Date\Date;
use Ryra\Core\App;

class FileSessionHandler implements \Ryra\Core\Contracts\Session\SessionHandler
{
    public function __construct(
        protected App $app,
        protected string $path,
        protected int $minutes
    ) {}

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $sessionId): string
    {
        $path = join_paths($this->path, $sessionId);
        return file_exists($path) ? file_get_contents($path) : '';
    }

    public function write(string $sessionId, string $data): bool
    {
        return (bool) file_put_contents(join_paths($this->path, $sessionId), $data, LOCK_EX);
    }

    public function destroy(string $sessionId): bool
    {
        return true;
    }

    public function gc(int $lifetime): int
    {
        $files = Finder::create()
            ->in($this->path)
            ->files()
            ->ignoreDotFiles(true)
            ->date("<= now - $lifetime seconds");

        $deletedSessions = 0;
        foreach ($files as $file) {
            unlink($file);
            $deletedSessions++;
        }
        return $deletedSessions;
    }

    /**
     * Get the current system time as a UNIX timestamp.
     */
    protected function currentTime(): int
    {
        return Date::now()->getTimestamp();
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
}