<?php

namespace Ryra\Core\HttpClient\Exception;

class InvalidUrlException extends \InvalidArgumentException
{
    public static function for(string $url): self
    {
        return new self("Invalid or non-absolute URL: \"{$url}\"");
    }

    public static function unsupportedScheme(string $scheme, string $url): self
    {
        return new self("Unsupported scheme \"{$scheme}\" in URL \"{$url}\". Only http and https are supported.");
    }
}
