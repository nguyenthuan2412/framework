<?php

namespace Ryra\Core\Messenger;

interface MessageBusInterface
{
    public function dispatch(object $command): Envelope;
}