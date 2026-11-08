<?php

namespace Ryra\Core\ErrorHandler;

use Ryra\Core\ErrorHandler\ErrorRenderer\CliErrorRenderer;

class Handler
{
    public function __construct()
    {
        error_reporting(0);
    }

    public function registerErrorHandler(): void
    {
        set_error_handler(static function (int $errno, string $errstr, ?string $errfile = null, ?int $errline = null) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        register_shutdown_function(static function () {
            $error = error_get_last();
            if ($error !== null) {
                $type = $error['type'];
                $errstr = $error['message'];
                $file = $error['file'];
                $line = $error['line'];
                throw new \ErrorException($errstr, 0, $type, $file, $line);
            }
        });
    }

    public function registerExceptionHandler(): void
    {
        set_exception_handler(static fn (\Throwable $throwable) => CliErrorRenderer::render($throwable));
    }
}