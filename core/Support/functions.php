<?php

use Ryra\Core\Support\Str;
use Ryra\Core\Support\Stringable;

function str(string $string): Stringable
{
    return Str::of($string);
}