<?php

namespace Ryra\Core\Log;

use Monolog\Logger as MonoLogger;
use DateTimeZone;

class Logger extends MonoLogger
{
    public function __construct(string $name, array $handlers = [], array $processors = [], ?DateTimeZone $timezone = null)
    {
        parent::__construct($name, $handlers, $processors, $timezone);

        $this->useLoggingLoopDetection(false);
    }
}