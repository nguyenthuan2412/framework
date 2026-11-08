<?php

namespace Ryra\Core\Jwt;

use Stringable;
use Ryra\Core\Jwt\Signer\SignerFactory;

class Builder implements Stringable
{
    private array $payload = [];

    private array $headers = [];

    private string $key    = '';

    private string $alg    = 'HS256';

    public static function create(): static
    {
        return new static();
    }

    /** Issuer: ai phát hành token */
    public function issuedBy(string $iss): static
    {
        $this->payload['iss'] = $iss;
        return $this;
    }

    /** Subject: đối tượng token đại diện (vd: user_id) */
    public function relatedTo(string|int $sub): static
    {
        $this->payload['sub'] = $sub;
        return $this;
    }

    /** Audience: ai được nhận token */
    public function permittedFor(string|array $aud): static
    {
        $this->payload['aud'] = $aud;
        return $this;
    }

    /** Issued At: mặc định là now nếu không set */
    public function issuedAt(int|\DateTimeInterface $iat): static
    {
        $this->payload['iat'] = $iat instanceof \DateTimeInterface ? $iat->getTimestamp() : $iat;
        return $this;
    }

    /** Not Before: token chưa hợp lệ trước thời điểm này */
    public function canOnlyBeUsedAfter(int|\DateTimeInterface $nbf): static
    {
        $this->payload['nbf'] = $nbf instanceof \DateTimeInterface ? $nbf->getTimestamp() : $nbf;
        return $this;
    }

    /** Expiration: thời điểm hết hạn (unix timestamp hoặc DateTimeInterface) */
    public function expiresAt(int|\DateTimeInterface $exp): static
    {
        $this->payload['exp'] = $exp instanceof \DateTimeInterface ? $exp->getTimestamp() : $exp;
        return $this;
    }

    /** Expiration: hết hạn sau N giây kể từ bây giờ */
    public function expiresIn(int $seconds): static
    {
        $this->payload['exp'] = \time() + $seconds;
        return $this;
    }

    /** JWT ID: định danh duy nhất của token */
    public function identifiedBy(string $jti): static
    {
        $this->payload['jti'] = $jti;
        return $this;
    }

    /** Issued At: set về thời điểm hiện tại */
    public function issuedNow(): static
    {
        $this->payload['iat'] = \time();
        return $this;
    }

    /** Key ID: định danh key dùng để ký (header kid) */
    public function withKeyId(string $kid): static
    {
        $this->headers['kid'] = $kid;
        return $this;
    }

    /** Thêm custom header claim (vd: kid, x5t...) */
    public function withHeader(string $name, mixed $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /** Thêm nhiều custom header claims cùng lúc */
    public function withHeaders(array $headers): static
    {
        $this->headers = \array_merge($this->headers, $headers);
        return $this;
    }

    public function getHeader(): array
    {
        return \array_merge(['typ' => 'JWT', 'alg' => $this->alg], $this->headers);
    }

    public function withClaim(string $name, mixed $value): static
    {
        $this->payload[$name] = $value;
        return $this;
    }

    public function withClaims(iterable $claims): static
    {
        foreach ($claims as $key => $value) {
            $this->payload[$key] = $value;
        }
        return $this;
    }

    public function signedWith(string $key, string $alg = 'HS256'): static
    {
        $this->key = $key;
        $this->alg = $alg;
        return $this;
    }

    public function toString(): string
    {
        if ($this->key === '') {
            throw new \RuntimeException('Signing key is required. Call signedWith() before toString().');
        }

        $this->payload['iat'] = $this->payload['iat'] ?? time();

        $encodedHeader  = Encoder::json($this->getHeader());
        $encodedPayload = Encoder::json($this->payload);

        $data      = "$encodedHeader.$encodedPayload";
        $signer    = SignerFactory::make($this->alg);
        $signature = $signer->sign($data, $this->key);

        return "$data." . Encoder::base64($signature);
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
