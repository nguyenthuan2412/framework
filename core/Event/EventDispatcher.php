<?php

namespace Ryra\Core\Event;

use Ryra\Core\App;

class EventDispatcher implements \Ryra\Core\Contracts\Event\EventDispatcher
{
    private array $listeners = [];

    private array $wildcardEvents = [];

    public function dispatch(string|object $event, array $payload = []): ?object
    {
        if (\is_string($event) && \class_exists($event)) {
            $event = App::make($event);
            \array_unshift($payload, $event);
        } elseif (\is_object($event)) {
            \array_unshift($payload, $event);
        }

        $listeners = $this->getListenersForEvent($event);
        foreach ($listeners as $listener) {
            if (\is_callable($listener)) {
                $stopPropagation = $listener(...$payload);
            } else {
                $stopPropagation = App::make($listener[0])->{$listener[1]}(...$payload);
            }
            if ($stopPropagation === false) {
                return \is_object($event) ? $event : null;
            }
        }
        return \is_object($event) ? $event : null;
    }

    public function getListenersForEvent(string|object $event): array
    {
        if (\is_object($event)) {
            $event = \get_class($event);
        }
        if (isset($this->listeners[$event])) {
            return $this->listeners[$event];
        }
        return [];
    }

    public function listen(string $event, array|callable $callable): static
    {
        $event = \trim($event);
        if (\str_contains($event, '*')) {
            $event = \str_replace('*', '.*', $event);
            $event = \str_replace('.', '\.', $event);
            if (!\in_array($event, $this->wildcardEvents)) {
                $this->wildcardEvents[$event] = $callable;
            }

            foreach (\array_keys($this->listeners) as $eventName) {
                if (\preg_match("/$event/", $eventName)) {
                    $this->listeners[$eventName][] = $callable;
                }
            }
            return $this;
        }

        foreach ($this->wildcardEvents as $wildcardEvent => $handler) {
            if (\preg_match("/$wildcardEvent/", $event)) {
                $this->listeners[$event][] = $handler;
            }
        }
        $this->listeners[$event][] = $callable;
        return $this;
    }

    public function subscriber(string $subscriber): static
    {
        $listeners = $subscriber::getSubscribedEvents();
        foreach ($listeners as $event => $listener) {
            if (\is_array($listener)) {
                foreach ($listener as $method) {
                    $this->listeners[$event][] = [$subscriber, $method];
                }
            } else {
                $this->listeners[$event][] = [$subscriber, $listener];
            }
        }
        return $this;
    }

    public function clearListeners(string $event): void
    {
        if (\array_key_exists($event, $this->listeners)) {
            unset($this->listeners[$event]);
        }
    }
}