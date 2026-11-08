<?php

namespace Ryra\Core\Server;

interface Taskable
{
    public function process(mixed $data): mixed;
}