<?php

namespace Ryra\Core\Twig;

class TwigConfig
{
    public function __construct(protected array $config) {}

    public function globalVars(): array
    {
        $this->config['globals'];
    }

    public function isDebug(): bool
    {
        $this->config['options']['debug'];
    }

    public function dirCache(): string
    {
        $this->config['options']['cache'];
    }

    public function autoReload(): bool
    {
        $this->config['options']['auto_reload'];
    }

    public function extensions(): array
    {
        $this->config['extensions'];
    }

    public function autoescape(): string
    {
        $this->config['autoescape'];
    }

    public function strictVariables(): bool
    {
        $this->config['strict_variables'];
    }
}