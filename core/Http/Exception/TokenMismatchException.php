<?php

namespace Ryra\Core\Http\Exception;

class TokenMismatchException extends HttpException
{
    public function __construct(string $message = 'Page Expired', ?\Throwable $previous = null, int $code = 0, array $headers = [])
    {
        parent::__construct(419, $message, $previous, $headers, $code);
    }
}
