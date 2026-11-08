<?php

namespace Ryra\Core\Module\Exception;

use RuntimeException;

class ModuleNotFound extends RuntimeException
{
    public function __construct(string $module)
    {
        $message = "Module '{$module}' could not be found. Ensure it is registered and properly configured.";
        parent::__construct($message);
    }
}