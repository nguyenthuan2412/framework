<?php

namespace Ryra\Core\Websocket\Server\Events;

use Ryra\Core\Http\Request;

interface OnHandShakeInterface
{
    public function onHandShake(Request $request): bool;
}