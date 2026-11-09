<?php

namespace Ryra\Core\Grpc\Exception;

use Ryra\Core\Grpc\Server\Code;

/**
 * Ném trong service để trả về lỗi gRPC cho client.
 *
 *   throw new GrpcException(Code::NOT_FOUND, 'User not found');
 *   throw GrpcException::notFound('User not found');
 */
class GrpcException extends \RuntimeException
{
    public function __construct(
        public readonly Code $status,
        string $message = '',
        public readonly array $metadata = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status->value, $previous);
    }

    public static function invalidArgument(string $message = 'Invalid argument'): static
    {
        return new static(Code::INVALID_ARGUMENT, $message);
    }

    public static function notFound(string $message = 'Not found'): static
    {
        return new static(Code::NOT_FOUND, $message);
    }

    public static function alreadyExists(string $message = 'Already exists'): static
    {
        return new static(Code::ALREADY_EXISTS, $message);
    }

    public static function permissionDenied(string $message = 'Permission denied'): static
    {
        return new static(Code::PERMISSION_DENIED, $message);
    }

    public static function unauthenticated(string $message = 'Unauthenticated'): static
    {
        return new static(Code::UNAUTHENTICATED, $message);
    }

    public static function unimplemented(string $message = 'Unimplemented'): static
    {
        return new static(Code::UNIMPLEMENTED, $message);
    }

    public static function internal(string $message = 'Internal error'): static
    {
        return new static(Code::INTERNAL, $message);
    }

    public static function unavailable(string $message = 'Unavailable'): static
    {
        return new static(Code::UNAVAILABLE, $message);
    }
}
