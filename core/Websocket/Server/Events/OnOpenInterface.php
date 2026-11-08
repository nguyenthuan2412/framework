<?php

namespace Ryra\Core\Websocket\Server\Events;

use Swoole\WebSocket\Server;
use Ryra\Core\Http\Request;

interface OnOpenInterface
{
    public function onOpen(Server $server, Request $request): void;
}