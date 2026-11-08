<?php

namespace Ryra\Core\Session\Middleware;

use Ryra\Core\Date\Date;
use Ryra\Core\Http\Cookie;
use Ryra\Core\Http\Request;
use Ryra\Core\Http\Response;
use Ryra\Core\Coroutine\Coroutine;
use Ryra\Core\Session\SessionManager;

class StartSession
{
    public function __construct(protected SessionManager $manager)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $session = $this->manager->driver();
        $session->start();

        Coroutine::defer(function () use ($session) {
            $session->save();
        });

        $request->setSession($session);
        $session->setCurrentUrl($request->fullUrl());
        $response = $next($request);

        $config = $this->manager->getSessionConfig();
        $response->withCookie(
            $session->getName(),
            $session->getId(),
            $this->getCookieExpirationDate(),
            $config['path'],
            $config['domain'],
            $config['secure'],
            $config['http_only'] ?? true,
            false,
            $config['same_site'] ?? null,
            $config['partitioned'] ?? false
        );
        return $response;
    }

    /**
     * Get the cookie lifetime in seconds.
     */
    protected function getCookieExpirationDate(): Date|int
    {
        $config = $this->manager->getSessionConfig();

        return $config['expire_on_close'] ? 0 : Date::instance(
            Date::now()->addRealMinutes($config['lifetime'])
        );
    }

    public function __destruct()
    {
        list($probability, $total) = config('session.lottery');
        if (random_int(1, $total) <= $probability) {
            $this->manager->driver()->getHandler()->gc(config('session.lifetime') * 60);
        }
    }
}