<?php

namespace Ryra\Core\Http;

use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Request as SwooleRequest;
use Ryra\Core\Server\Attributes\Event;
use Ryra\Core\Server\AbstractServer;
use Ryra\Core\Coroutine\Coroutine;
use Swoole\Server as SwooleServer;
use Ryra\Core\Context\Context;
use Ryra\Core\ErrorHandler\ErrorRenderer\CliErrorRenderer;

class Server extends AbstractServer
{
    protected function createServer(array $configs): SwooleServer
    {
        $swooleServer = new \Swoole\Http\Server(
            $configs['host'],
            $configs['port'],
            $configs['mode'],
            $configs['socket_type'],
        );
        $swooleServer->set($configs['configs']);
        return $swooleServer;
    }

    #[Event('Request')]
    public function onRequest(SwooleRequest $swooleRequest, SwooleResponse $swooleResponse): void
    {
        try {
            Coroutine::defer([$this, 'terminateRequest']);
            $request = $this->app->get('request');
            $request->initRequest($swooleRequest);
            $response = $this->app->get('route.dispatcher')->handle($request);
            $response->prepare($request);
            $response->send($swooleResponse);
        } catch (\Throwable $th) {
            CliErrorRenderer::render($th);
            $swooleResponse->status(500);
            $swooleResponse->end(json_encode([
                'message' => 'Server error'
            ]));
        }
    }

    public function terminateRequest(): void
    {
        $terminateRequest = $this->app->getTerminateRequest();
        if (!empty($terminateRequest)) {
            $terminateRequest($this->app);
        }
        $this->app->forgetScopedInstances();
        Context::delete();
    }
}