<?php

namespace Ryra\Core\Jwt;

use Stringable;

class Token implements Stringable
{
    public function __construct(
        private array $header,
        private array $payload,
        private string $token,
    ) {}

    public function headers(): array
    {
        return $this->header;
    }

    public function claims(): array
    {
        return $this->payload;
    }

    public function header(string $key): mixed
    {
        return $this->header[$key] ?? null;
    }

    public function get(string $key): mixed
    {
        return $this->payload[$key] ?? null;
    }

    public function issuedBy(): ?string
    {
        return $this->payload['iss'] ?? null;
    }

    public function relatedTo(): mixed
    {
        return $this->payload['sub'] ?? null;
    }

    public function permittedFor(): array
    {
        $aud = $this->payload['aud'] ?? [];
        return \is_array($aud) ? $aud : [$aud];
    }

    public function issuedAt(): ?int
    {
        return isset($this->payload['iat']) ? (int) $this->payload['iat'] : null;
    }

    public function expiresAt(): ?int
    {
        return isset($this->payload['exp']) ? (int) $this->payload['exp'] : null;
    }

    public function notBefore(): ?int
    {
        return isset($this->payload['nbf']) ? (int) $this->payload['nbf'] : null;
    }

    public function identifiedBy(): ?string
    {
        return isset($this->payload['jti']) ? (string) $this->payload['jti'] : null;
    }

    public function isIssuedBy(string $iss): bool
    {
        return $this->issuedBy() === $iss;
    }

    public function isPermittedFor(string $aud): bool
    {
        return \in_array($aud, $this->permittedFor(), true);
    }

    public function isRelatedTo(string $sub): bool
    {
        return (string) $this->relatedTo() === $sub;
    }

    public function isExpired(int $leeway = 0): bool
    {
        $exp = $this->expiresAt();
        return $exp !== null && (\time() - $leeway) >= $exp;
    }

    public function isValidAt(int $timestamp, int $leeway = 0): bool
    {
        $nbf = $this->notBefore();
        if ($nbf !== null && $nbf > ($timestamp + $leeway)) {
            return false;
        }
        return !$this->isExpired($leeway);
    }

    public function toString(): string
    {
        return $this->token;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
