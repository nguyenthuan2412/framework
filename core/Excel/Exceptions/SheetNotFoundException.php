<?php

namespace Ryra\Core\Excel\Exceptions;

class SheetNotFoundException extends ExcelException
{
    public static function byIndex(int $index): self
    {
        return new self("Sheet with index [{$index}] not found.");
    }

    public static function byName(string $name): self
    {
        return new self("Sheet [{$name}] not found.");
    }
}
