<?php

namespace Ryra\Core\Support;

interface Queryable extends \Stringable
{
    public function getBindings(): array;

    public function toSql(): string;
}