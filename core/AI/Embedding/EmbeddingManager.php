<?php

namespace Ryra\Core\AI\Embedding;

use Closure;
use Ryra\Core\AI\Embedding\Drivers\GeminiEmbeddingDriver;
use Ryra\Core\AI\Embedding\Drivers\OpenAIEmbeddingDriver;
use Ryra\Core\AI\Embedding\Drivers\VoyageEmbeddingDriver;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\Contracts\AI\EmbeddingModel;
use Ryra\Core\Contracts\Config\Repository;
use Ryra\Core\Contracts\Container\Container;
use Ryra\Core\Support\Manager;

/**
 * @method array embed(string $text)
 * @method array embedBatch(array $texts)
 * @method int   dimensions()
 */
class EmbeddingManager extends Manager
{
    /**
     * Tên gọi khác của cùng một driver, quy về tên chuẩn trước khi đọc config.
     */
    protected const ALIASES = ['google' => 'gemini'];

    protected Repository $config;

    /**
     * @var array<string, Closure>
     */
    protected array $customCreators = [];

    /**
     * @var array<string, EmbeddingModel>
     */
    protected array $resolved = [];

    public function __construct(protected Container $container)
    {
        $this->config = $this->container->get('config');
    }

    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('ai.embedding.default', 'openai');
    }

    public function driver(?string $driver = null, array $params = []): mixed
    {
        $driver ??= $this->getDefaultDriver();
        $driver = static::ALIASES[$driver] ?? $driver;

        return $this->resolved[$driver] ??= $this->resolve($driver);
    }

    public function extend(string $driver, Closure $creator): static
    {
        $this->customCreators[$driver] = $creator;
        unset($this->resolved[$driver]);

        return $this;
    }

    protected function resolve(string $driver): EmbeddingModel
    {
        $config = $this->config->get("ai.embedding.drivers.{$driver}");

        if (!is_array($config)) {
            throw new AIException(
                sprintf('Embedding driver "%s" chưa được cấu hình trong config/ai.php.', $driver)
            );
        }

        if (isset($this->customCreators[$driver])) {
            return ($this->customCreators[$driver])($config, $this->container);
        }

        if (isset($config['driver']) && class_exists($config['driver'])) {
            return new $config['driver']($config);
        }

        return match ($driver) {
            'openai' => new OpenAIEmbeddingDriver($config),
            'gemini' => new GeminiEmbeddingDriver($config),
            'voyage' => new VoyageEmbeddingDriver($config),
            default => throw new AIException(
                sprintf('Embedding driver "%s" is not supported.', $driver)
            ),
        };
    }
}
