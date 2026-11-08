<?php

namespace Ryra\Core\Http\Exception;

class PreconditionRequiredHttpException extends HttpException
{
    public function __construct(string $message = '', ?\Throwable $previous = null, int $code = 0, array $headers = [])
    {
        parent::__construct(428, $message, $previous, $headers, $code);
    }
}
