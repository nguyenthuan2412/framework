<?php

namespace Ryra\Core\Jwt;

use Ryra\Core\Jwt\Exception\ExpiredException;
use Ryra\Core\Jwt\Exception\InvalidTokenException;
use Ryra\Core\Jwt\Exception\SignatureException;
use Ryra\Core\Jwt\Signer\SignerFactory;

class Config
{
    public function __construct(
        private string $key,
        private array $algorithms,
        private ?string $issuer = null,
        private string|array|null $audience = null,
        private int $leeway = 0
    ) {}

    public function parse(string $token): Token
    {
        $parts = \explode('.', $token);
        if (\count($parts) !== 3) {
            throw new InvalidTokenException('Invalid token format');
        }

        [$h64, $p64, $s64] = $parts;

        $header  = \json_decode(Encoder::base64Decode($h64), true);
        $payload = \json_decode(Encoder::base64Decode($p64), true);
        $sig     = Encoder::base64Decode($s64);

        if (!isset($header['alg']) || !in_array($header['alg'], $this->algorithms, true)) {
            throw new InvalidTokenException('Algorithm not allowed');
        }

        $data   = "$h64.$p64";
        $signer = SignerFactory::make($header['alg']);

        if (!$signer->verify($data, $sig, $this->key)) {
            throw new SignatureException('Invalid signature');
        }

        $now = \time();

        if (isset($payload['nbf']) && $payload['nbf'] > ($now + $this->leeway)) {
            throw new InvalidTokenException('Token not active yet');
        }

        if (isset($payload['exp']) && ($now - $this->leeway) >= $payload['exp']) {
            throw new ExpiredException('Token expired');
        }

        if ($this->issuer !== null && ($payload['iss'] ?? null) !== $this->issuer) {
            throw new InvalidTokenException('Invalid issuer');
        }

        if ($this->audience !== null) {
            $tokenAud = $payload['aud'] ?? null;
            $tokenAuds = \is_array($tokenAud) ? $tokenAud : [$tokenAud];
            $requiredAuds = \is_array($this->audience) ? $this->audience : [$this->audience];
            if (empty(\array_intersect($requiredAuds, $tokenAuds))) {
                throw new InvalidTokenException('Invalid audience');
            }
        }

        return new Token($header, $payload, $token);
    }
}
