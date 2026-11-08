<?php

namespace Ryra\Core\Config;

use Ryra\Core\Config\Exception\ConfigNotFoundException;

class Repository implements \Ryra\Core\Contracts\Config\Repository
{
    /**
     * Store the configs.
     *
     * @var array<string, mixed>
     */
    protected array $configs = [];

    /**
     * Store the original configs.
     *
     * @var array<string, mixed>
     */
    protected array $originalConfigs = [];

    /**
     * Get config by name.
     */
    public function get(string $name, mixed $default = null): mixed
    {
        if (isset($this->configs[$name])) {
            return $this->configs[$name];
        }

        if (isset($this->originalConfigs[$name])) {
            return $this->originalConfigs[$name];
        }

        return $default;
    }

    /**
     * Set config.
     */
    public function set(string $name, mixed $config): static
    {
        return $this->setConfig('configs', $name, $config);
    }

    /**
     * Set original config.
     */
    public function setOriginal(string $name, mixed $config): static
    {
        return $this->setConfig('originalConfigs', $name, $config);
    }

    protected function setConfig(string $storeName, string $name, mixed $config): static
    {
        $store = &$this->{$storeName};
        $keys = \explode('.', $name);
        if (\is_callable($config)) {
            $config = \Closure::fromCallable($config);
            $config = $config->bindTo($this);
            $config = $config();
        }

        if (\is_array($config)) {
            foreach ($store as $key => &$value) {
                if ($key === $name || \str_starts_with($key, "$name.")) {
                    unset($store[$key]);
                } else if (\str_starts_with($name, "$key.") && \is_array($value)) {
                    $childKeys = \array_slice($keys, \substr_count($key, '.') + 1);
                    $this->removeRecursive($value, $childKeys);
                    $this->addRecursive($value, $childKeys, $config);
                }
            }
            unset($value);
            $store = array_merge($store, $this->extractConfig($name, $config));
        } else {
            $firstKey = $keys[0];
            $store[$firstKey] = !empty($store[$firstKey]) ? $store[$firstKey] : [];
            $this->addRecursiveByKeys($store, $store[$firstKey], $firstKey, \array_slice($keys, 1), $config);
        }
        
        return $this;
    }

    /**
     * Add recursive by keys.
     */
    protected function addRecursiveByKeys(array &$store, array &$element, string $name, array $keys, mixed $config): void
    {
        $key = $keys[0];
        if (\count($keys) === 1) {
            $store[$name] = !empty($store[$name]) ? $store[$name] : [];
            $store[$name][$key] = $config;
            $element[$key] = $config;
        } else {
            $element[$key] = !empty($element[$key]) ? $element[$key] : [];
            $name .= ".$key";
            $this->addRecursiveByKeys($store, $element[$key], $name, \array_slice($keys, 1), $config);
        }
    }

    /**
     * Remove config by recursion.
     */
    protected function removeRecursive(array &$config, array $keys, int $level = 0): array
    {
        if ($level >= \count($keys)) {
            return $config;
        }

        $item = &$config[$keys[$level]];
        if (isset($item) && $level === \count($keys) - 1) {
            unset($item);
        } else if (isset($item)) {
            $item = $this->removeRecursive($item, $keys, $level + 1);
        }

        return $config;
    }

    /**
     * Add config by recursion.
     */
    protected function addRecursive(array &$config, array $keys, mixed $value, int $level = 0): void
    {
        if ($keys === []) {
            return;
        }

        if ($level === \count($keys) - 1) {
            $config[$keys[$level]] = $value;
        } else {
            if (!isset($config[$keys[$level]])) {
                $config[$keys[$level]] = [];
            }
            $this->addRecursive($config[$keys[$level]], $keys, $value, $level + 1);
        }
    }

    /**
     * Check has config.
     */
    public function has(string $name): bool
    {
        return isset(($this->configs[$name]));
    }

    /**
     * Copy config.
     * 
     * @throws \Ryra\Core\Config\Exception\ConfigNotFoundException If config file not found
     */
    public function copy(string $from, string $to): bool
    {
        if (!\file_exists($from)) {
            throw new ConfigNotFoundException("Config file $from not found");
        }

        return \copy($from, $to);
    }

    /**
     * Extract config.
     */
    protected function extractConfig(string $configName, array $config): array
    {
        $configsExtracted = [];
        $configsExtracted[$configName] = $config;
        foreach ($config as $key => $value) {
            if (\is_array($value)) {
                $configsExtracted = [...$configsExtracted, ...$this->extractConfig("$configName.$key", $value)];
            }

            $configsExtracted["$configName.$key"] = $value;
        }

        return $configsExtracted;
    }
}