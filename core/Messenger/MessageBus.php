<?php

namespace Ryra\Core\Messenger;

use Ryra\Core\Contracts\Container\Container;

class MessageBus implements MessageBusInterface
{
    protected array $middlewares = [];

    public function __construct(protected Container $container) {}

    public function dispatch(object $message): Envelope
    {
        return new Envelope($message, middlewares: $this->middlewares);
    }
}