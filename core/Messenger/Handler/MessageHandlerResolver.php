<?php

namespace Ryra\Core\Messenger\Handler;

use Ryra\Core\Messenger\Exception\InvalidMessageHandlerException;
use Ryra\Core\Contracts\Container\Container;

class MessageHandlerResolver implements MessageHandlerResolverInterface
{
    private array $handlers = [];

    public function __construct(private Container $container) {}

    public function addHandler(string $message, string $messageHandler): void
    {
        if (!method_exists($messageHandler, '__invoke')) {
            throw new InvalidMessageHandlerException(sprintf('Handler "%s" is not callable.', $messageHandler));
        }

        $this->handlers[$message] = $messageHandler;
    }

    public function resolve(object $message): ?callable
    {
        $messageClass = get_class($message);
        if (isset($this->handlers[$messageClass])) {
            $handlerClass = $this->handlers[$messageClass];
            return $this->container->make($handlerClass);
        }

        return null;
    }

    public function hasHandler(string|object $message): bool
    {
        $messageClass = get_class($message);
        return isset($this->handlers[$messageClass]);
    }
}