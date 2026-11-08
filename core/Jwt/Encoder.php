<?php

namespace Ryra\Core\Jwt;

class Encoder
{
    public static function json(array $data): string
    {
        return self::base64(
            \json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public static function base64(string $data): string
    {
        return \rtrim(\strtr(\base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64Decode(string $data): string
    {
        $pad = \strlen($data) % 4;
        if ($pad !== 0) {
            $data .= \str_repeat('=', 4 - $pad);
        }

        return \base64_decode(\strtr($data, '-_', '+/'));
    }
}