<?php

namespace Ryra\Core\Contracts\Pool;

interface ConnectionFactory
{
    public function create(): object;

    public function destroy(object $client): void;

    public function ping(object $client): bool;

    public function reset(object $client): void;

    public function getLastQueryTime(object $client): int;
}