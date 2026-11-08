<?php

namespace Ryra\Core\Messenger\Middleware;

abstract class BaseMiddleware
{
    abstract public function handle(object $message, callable $next): void;
}