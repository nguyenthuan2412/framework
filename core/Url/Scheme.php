<?php

namespace Ryra\Core\Url;

enum Scheme: string
{
    case Http = 'http';
    case Https = 'https';
    case Ftp = 'ftp';
    case Ftps = 'ftps';
    case Sftp = 'sftp';
    case Ws = 'ws';
    case Wss = 'wss';
    case Mailto = 'mailto';
    case Tel = 'tel';
    case Ssh = 'ssh';
    case File = 'file';

    public function defaultPort(): ?int
    {
        return match ($this) {
            self::Http, self::Ws => 80,
            self::Https, self::Wss => 443,
            self::Ftp => 21,
            self::Ftps => 990,
            self::Sftp, self::Ssh => 22,
            default => null,
        };
    }

    public function isSecure(): bool
    {
        return match ($this) {
            self::Https,
            self::Ftps,
            self::Sftp,
            self::Wss,
            self::Ssh => true,
            default => false,
        };
    }

    public function usesAuthority(): bool
    {
        return match ($this) {
            self::Mailto, self::Tel => false,
            default => true,
        };
    }

    public function toSecure(): self
    {
        return match ($this) {
            self::Http => self::Https,
            self::Ws => self::Wss,
            self::Ftp => self::Ftps,
            default => $this,
        };
    }

    public function isDefaultPort(int $port): bool
    {
        return $this->defaultPort() === $port;
    }

    public static function tryFromString(string $scheme): ?self
    {
        return self::tryFrom(\strtolower($scheme));
    }
}
