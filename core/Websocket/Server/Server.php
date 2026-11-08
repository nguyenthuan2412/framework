<?php

namespace Ryra\Core\Websocket\Server;

use Ryra\Core\Websocket\Server\Events\OnHandShakeInterface;
use Ryra\Core\Websocket\Server\Events\OnMessageInterface;
use Ryra\Core\Websocket\Server\Events\OnCloseInterface;
use Ryra\Core\Websocket\Server\Collector\FdCollector;
use Ryra\Core\Http\Server as HttpServer;
use Ryra\Core\Server\Attributes\Event;
use Ryra\Core\Coroutine\Coroutine;
use Swoole\Server as SwooleServer;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as WebSocketServer;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Ryra\Core\Log\Log;

class Server extends HttpServer
{
    protected function createServer(array $configs): SwooleServer
    {
        $swooleServer = new WebSocketServer(
            $configs['host'],
            $configs['port'],
            $configs['mode'],
            $configs['socket_type'],
        );
        $swooleServer->set($configs['configs']);
        return $swooleServer;
    }

    #[Event('Message')]
    public function onMessage(WebSocketServer $server, Frame $frame)
    {
        Coroutine::defer([$this, 'terminateRequest']);
        $controller = FdCollector::get($frame->fd);

        if (is_a($controller, OnMessageInterface::class, true)) {
            $this->app->make($controller)->onMessage($server, $frame);
        }
    }

    #[Event('Handshake')]
    public function onHandshake(SwooleRequest $swooleRequest, SwooleResponse $swooleResponse)
    {
        Coroutine::defer([$this, 'terminateRequest']);
        /** @var Request */
        $request = $this->app->get('request');
        $request->initRequest($swooleRequest);
        $secWebSocketKey = $request->header('sec-websocket-key');
        $security = $this->app->get(Security::class);
        if (
            empty($secWebSocketKey)
            && !$security->isInvalidSecurityKey($secWebSocketKey)
        ) {
            $swooleResponse->status(403);
            $swooleResponse->end();
            return false;
        }

        try {
            $dispatcher = $this->app->get('route.dispatcher');
            $routeInfo = $dispatcher->dispatch($request->method(), $request->uri());
            if ($routeInfo[0] == \FastRoute\Dispatcher::FOUND) {
                /** @var Route */
                $route = $routeInfo[1];
                $routeParams = $routeInfo[2];
                $route->setParams($routeParams);
                $action = $route->getAction();

                /** @var string */
                [$controller] = $action;
                FdCollector::set($swooleRequest->fd, $controller);

                if (is_a($controller, OnHandShakeInterface::class, true)) {
                    $controller = $this->app->make($controller);
                    $success = $controller->onHandShake($request);
                    if (!$success) {
                        $swooleResponse->end();
                        return false;
                    }
                }
            }

            $headers = $security->handshakeHeaders($secWebSocketKey);
            foreach ($headers as $key => $value) {
                $swooleResponse->setHeader($key, $value);
            }
            $swooleResponse->status(101);
        } catch (\Throwable $th) {
            FdCollector::del($swooleRequest->fd);
            $swooleResponse->write($th->getMessage());
            $swooleResponse->end();
        }

        return true;
    }

    #[Event('Close')]
    public function onClose(WebSocketServer $server, int $fd, int $reactorId): void
    {
        Coroutine::defer([$this, 'terminateRequest']);
        try {
            $controller = FdCollector::get($fd);

            if (is_a($controller, OnCloseInterface::class, true)) {
                $this->app->make($controller)->onClose($server, $fd, $reactorId);
            }
        } catch (\Throwable $th) {
            Log::error($th);
        }

        FdCollector::del($fd);
    }
}