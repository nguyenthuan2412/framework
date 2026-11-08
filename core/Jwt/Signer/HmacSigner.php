<?php

namespace Ryra\Core\Jwt\Signer;

final class HmacSigner implements SignerInterface
{
    public function __construct(private string $algo) {}

    public function sign(string $data, string $key): string
    {
        return \hash_hmac($this->algo, $data, $key, true);
    }

    public function verify(string $data, string $signature, string $key): bool
    {
        return \hash_equals($signature, $this->sign($data, $key));
    }
}
