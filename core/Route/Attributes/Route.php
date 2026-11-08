<?php

namespace Ryra\Core\Route\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route extends Get
{
    protected string $uri;

    public function __construct(
        protected string|array $methods,
        string $uri,
        protected ?string $name = null,
        protected ?string $summary = null,
        protected ?string $description = null,
        protected bool $deprecated = false,
        protected array $middlewares = [],
        protected array $excludedMiddlewares = [],
    )
    {
        $this->uri = '/' . trim($uri, '/');
    }

    public function getMethod(): string|array
    {
        return $this->methods;
    }
}