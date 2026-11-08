<?php

namespace Ryra\Core\Contracts\Event;

interface EventDispatcher
{
    public function dispatch(string|object $event, array $payload = []): ?object;

    public function getListenersForEvent(string|object $event): array;

    public function listen(string $event, array|callable $callable): static;

    public function subscriber(string $subscriber): static;

    public function clearListeners(string $event): void;
}
