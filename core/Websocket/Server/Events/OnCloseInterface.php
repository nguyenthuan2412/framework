<?php

namespace Ryra\Core\Websocket\Server\Events;

use Swoole\WebSocket\Server;

interface OnCloseInterface
{
    public function onClose(Server $server, int $fd, int $reactorId): void;
}