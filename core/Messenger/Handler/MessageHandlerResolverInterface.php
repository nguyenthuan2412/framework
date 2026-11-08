<?php

namespace Ryra\Core\Messenger\Handler;

interface MessageHandlerResolverInterface
{
    public function addHandler(string $message, string $messageHandler): void;

    public function resolve(object $message): ?callable;
    
    public function hasHandler(string|object $message): bool;
}