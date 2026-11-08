<?php

namespace Ryra\Core\Auth\Exception;

use Ryra\Core\Http\Exception\HttpException;

class AuthenticationException extends HttpException
{
    public function __construct(
        string $message = 'Unauthenticated.',
        protected ?string $guard = null,
        array $headers = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(401, $message, $previous, $headers);
    }

    /**
     * Guard đã từ chối request.
     */
    public function getGuard(): ?string
    {
        return $this->guard;
    }
}
