<?php

namespace Ryra\Core\Contracts\Event;

interface EventSubscriber
{
    public function subscribe(EventDispatcher $dispatcher): void;

    public static function getSubscribedEvents(): array;
}