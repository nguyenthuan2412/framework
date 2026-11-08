<?php

namespace Ryra\Core\Event;

class NullDispatcher implements \Ryra\Core\Contracts\Event\EventDispatcher
{
    public function dispatch(string|object $event, array $payload = []): ?object
    {
        return null;
    }

    public function getListenersForEvent(string|object $event): array
    {
        return [];
    }

    public function listen(string $event, array|callable $callable): static
    {
        return $this;
    }

    public function subscriber(string $subscriber): static
    {
        return $this;
    }

    public function clearListeners(string $event): void
    {
        return;
    }
}