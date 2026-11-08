<?php

namespace Ryra\Modules\Example\Services;

class GreetingService
{
    public function greet(string $name): string
    {
        return "Xin chào, {$name}!";
    }
}
