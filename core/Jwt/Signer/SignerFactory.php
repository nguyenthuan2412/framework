<?php

namespace Ryra\Core\Jwt\Signer;

use Ryra\Core\Jwt\Exception\InvalidTokenException;

class SignerFactory
{
    private static array $map = [
        'HS256' => ['hmac', 'sha256'],
        'HS384' => ['hmac', 'sha384'],
        'HS512' => ['hmac', 'sha512'],
        'RS256' => ['rsa', OPENSSL_ALGO_SHA256],
        'RS384' => ['rsa', OPENSSL_ALGO_SHA384],
        'RS512' => ['rsa', OPENSSL_ALGO_SHA512],
    ];

    public static function make(string $alg)
    {
        if (!isset(self::$map[$alg])) {
            throw new InvalidTokenException('Unsupported algorithm');
        }

        [$type, $algo] = self::$map[$alg];

        return match ($type) {
            'hmac' => new HmacSigner($algo),
            'rsa'  => new RsaSigner($algo),
        };
    }
}
