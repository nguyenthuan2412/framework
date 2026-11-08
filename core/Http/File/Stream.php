<?php

namespace Ryra\Core\Http\File;

class Stream extends File
{
    public function getSize(): int|false
    {
        return false;
    }
}