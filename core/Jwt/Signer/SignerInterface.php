<?php

namespace Ryra\Core\Jwt\Signer;

interface SignerInterface
{
    public function sign(string $data, string $key): string;

    public function verify(string $data, string $signature, string $key): bool;
}
