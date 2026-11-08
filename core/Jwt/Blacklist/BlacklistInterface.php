<?php

namespace Ryra\Core\Jwt\Blacklist;

use Ryra\Core\Jwt\Token;

interface BlacklistInterface
{
    public function add(Token $token): void;

    public function has(Token $token): bool;

    public function flush(): void;
}
