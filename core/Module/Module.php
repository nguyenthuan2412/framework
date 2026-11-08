<?php

namespace Ryra\Core\Module;

class Module
{
    protected string $id;

    protected string $name;

    protected string $description;

    protected string $pathModule;

    protected array $configs;

    public function __construct(string $pathFileModule)
    {
        $infoModule = require $pathFileModule;
        $this->id = basename($pathFileModule, '.module.php');
        $this->pathModule = dirname($pathFileModule);

        if (!isset($infoModule['name'])) {
            $module = ucfirst($this->id);
            throw new \Exception("$module module missing name field");
        }

        $this->configs = $infoModule;
    }

    public function getPath(): string
    {
        return $this->pathModule;
    }

    public function getName(): string
    {
        return $this->configs['name'];
    }

    public function getConfig(string $key): mixed
    {
        return $this->configs[$key] ?? false;
    }

    public function getDescription(): string
    {
        return $this->configs['description'] ?? '';
    }

    public function providers(): array
    {
        return $this->configs['providers'] ?? [];
    }

    public function getPrefix(): string
    {
        return $this->configs['prefix'] ?? '';
    }

    public function getId(): string
    {
        return $this->id;
    }
}