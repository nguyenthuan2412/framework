<?php

namespace Ryra\Core\Websocket\Server\Events;

use Swoole\WebSocket\Server;
use Swoole\WebSocket\Frame;

interface OnMessageInterface
{
    public function onMessage(Server $server, Frame $frame): void;
}
