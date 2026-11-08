<?php

namespace Ryra\Core\Contracts\HttpClient;

use Ryra\Core\Collection\Collection;
use ArrayAccess;
use Stringable;

interface Response extends ArrayAccess, Stringable
{
    public function body(): string;

    public function isJson(): bool;

    public function json(string|int|null $key = null, mixed $default = null): mixed;

    public function object(): object|null;

    public function collect(string|int|null $key = null): Collection;

    public function status(): int;

    public function headers(): array;

    public function header(string $key): mixed;

    public function ok(): bool;

    public function successful(): bool;

    public function redirect(): bool;

    public function created(): bool;

    public function accepted(): bool;

    public function noContent(): bool;

    public function movedPermanently(): bool;

    public function found(): bool;

    public function badRequest(): bool;

    public function unauthorized(): bool;

    public function paymentRequired(): bool;

    public function forbidden(): bool;

    public function notFound(): bool;

    public function requestTimeout(): bool;

    public function conflict(): bool;

    public function unprocessableEntity(): bool;

    public function tooManyRequests(): bool;

    public function serverError(): bool;

    public function clientError(): bool;

    public function failed(): bool;

    public function throw(): static;

    public function throwIf(bool $condition): static;
}