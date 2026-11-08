<?php

namespace Ryra\Core\AI\Embedding\Drivers;

use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Http\Transport;
use Ryra\Core\Contracts\AI\EmbeddingModel;

abstract class AbstractEmbeddingDriver implements EmbeddingModel
{
    protected Transport $transport;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(protected array $config, ?Transport $transport = null)
    {
        $this->transport = $transport ?? new Transport(
            $this->name(),
            (float) ($config['timeout'] ?? 60),
            (float) ($config['connect_timeout'] ?? 10),
            (int) ($config['retries'] ?? 2),
        );
    }

    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0] ?? [];
    }

    public function dimensions(): int
    {
        return (int) ($this->config['dimensions'] ?? 0);
    }

    protected function apiKey(): string
    {
        $key = (string) ($this->config['api_key'] ?? '');

        if ($key === '') {
            throw new AIException(
                sprintf('Missing API key for embedding driver "%s".', $this->name())
            );
        }

        return $key;
    }

    protected function model(): string
    {
        $model = (string) ($this->config['model'] ?? '');

        if ($model === '') {
            throw new AIException(sprintf('No model configured for embedding driver "%s".', $this->name()));
        }

        return $model;
    }

    protected function baseUrl(string $default): string
    {
        return rtrim((string) ($this->config['base_url'] ?? $default), '/');
    }

    /**
     * @param array<int, string> $texts
     */
    protected function guardNotEmpty(array $texts): void
    {
        if ($texts === []) {
            throw new AIException('Cannot embed an empty list of texts.');
        }
    }
}
