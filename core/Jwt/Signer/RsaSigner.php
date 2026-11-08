<?php

namespace Ryra\Core\Jwt\Signer;

class RsaSigner implements SignerInterface
{
    public function __construct(
        private int $algo
    ) {}

    public function sign(string $data, string $privateKey): string
    {
        \openssl_sign($data, $signature, $privateKey, $this->algo);
        return $signature;
    }

    public function verify(string $data, string $signature, string $publicKey): bool
    {
        return \openssl_verify($data, $signature, $publicKey, $this->algo) === 1;
    }
}
