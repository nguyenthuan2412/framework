<?php

namespace Ryra\Core\SocketIO\Events;

use Swoole\WebSocket\Server;

class CloseEvent extends ServerEvent
{
    public function handle(Server $request, int $fd): bool
    {
        $socket = $this->socketManager->get($fd);
        if ($socket === null) {
            $socket = new Socket($this->server, $this->router, $this->rooms, $this->responseFactory);
            $socket->setFd($fd);
        }
        $disconnecting = new MessageRequest($socket, 'disconnecting', $fd, []);
        $this->router->dispatch($disconnecting);

        $this->pingTimerManager->remove($fd);
        $this->socketManager->del($fd);
        $rooms = $this->rooms->getFdRooms($fd);
        foreach ($rooms as $room) {
            $this->rooms->leave($room, $fd);
        }

        $disconnect = new MessageRequest($socket, 'disconnect', $fd, []);
        $this->router->dispatch($disconnect);
        unset($socket);
    }
}