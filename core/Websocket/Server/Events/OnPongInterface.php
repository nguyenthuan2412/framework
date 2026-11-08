<?php

namespace Ryra\Core\Websocket\Server\Events;

use Swoole\WebSocket\Server;
use Swoole\WebSocket\Frame;

interface OnPingInterface
{
    public function onPong(Server $server, Frame $frame): void;
}