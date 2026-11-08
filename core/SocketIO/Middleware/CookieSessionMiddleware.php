<?php

namespace Ryra\Core\SocketIO\Middleware;

use Swoole\Http\Response;
use Ryra\Core\SocketIO\SidGenerator;
use Ryra\Core\SocketIO\Socket;

class CookieSessionMiddleware
{
    public function handle(Socket $socket, Response $response, callable $next)
    {
        $request = $socket->getRequest();
        $sessionId = $request->cookie[$this->cookieName] ?? SidGenerator::generateFromSocket($socket, [['rand', 4]]);

        $session = $this->server->getSessionStorage()->getSession($sessionId);
        $session->commit();

        $socket->setSession($session);

        $response->setCookie($this->cookieName, $sessionId);

        $next();
    }
}
