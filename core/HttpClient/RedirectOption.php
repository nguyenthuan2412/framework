<?php

namespace Ryra\Core\HttpClient;

class RedirectOption
{
    public $onRedirect;

    public function __construct(
        public int $max,
        public bool $strict,
        public bool $referer,
        public array $protocols,
        public bool $trackRedirects
    ) {}
}