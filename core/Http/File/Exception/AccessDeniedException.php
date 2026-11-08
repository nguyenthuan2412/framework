<?php

namespace Ryra\Core\Http\File\Exception;

class AccessDeniedException extends FileException
{
    public function __construct(string $path)
    {
        parent::__construct(\sprintf('The file %s could not be accessed', $path));
    }
}