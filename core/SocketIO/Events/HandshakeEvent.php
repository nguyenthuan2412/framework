<?php

namespace Ryra\Core\SocketIO\Events;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Ryra\Core\Server\Events\ServerEvent;

class HandshakeEvent extends ServerEvent
{
    public function handle(Request $request, Response $response): bool
    {
        $secWebSocketKey = $request->header['sec-websocket-key'];
        $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';

        // At this stage if the socket request does not meet custom requirements, you can ->end() it here and return false...
        // Websocket handshake connection algorithm verification
        if (
            0 === preg_match($patten, $secWebSocketKey)
            || 16 !== strlen(base64_decode($secWebSocketKey))
        ) {
            $response->end();
            return false;
        }

        $key = base64_encode(
            sha1($request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)
        );

        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => $key,
            'Sec-WebSocket-Version' => '13',
        ];

        if(isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }

        foreach($headers as $key => $val) {
            $response->header($key, $val);
        }

        $socket = new Socket($this->server, $this->router, $this->rooms, $this->responseFactory);
        $socket->setFd($request->fd);
        $socket->setRequest($request);

        $continue = true;
        foreach ($this->handShakeMiddleware as $middleware) {
            $callback = $middleware[0];
            if ($callback instanceof MiddlewareInterface) {
                $callback = [$middleware[0], 'handle'];
            }

            call_user_func($callback, $socket, $response, function (?\Error $error = null) use ($socket, &$continue) {
                if ($error) {
                    $continue = false;
                }
            });

            if (!$continue) {
                break;
            }
        }

        if (!$continue) {
            $response->status(400);
            $response->end();

            return false;
        }

        $response->status(101);
        $response->end();

        \Swoole\Event::defer(function () use ($socket) {
            $this->socketManager->add($socket);
            call_user_func($this->server->getCallback('Open'), $this->server, $socket);
        });

        return true;
    }
}