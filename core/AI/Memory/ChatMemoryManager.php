<?php

namespace Ryra\Core\AI\Memory;

use Closure;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Memory\Repository\DatabaseChatMemoryRepository;
use Ryra\Core\AI\Memory\Repository\InMemoryChatMemoryRepository;
use Ryra\Core\Contracts\AI\ChatMemory;
use Ryra\Core\Contracts\AI\ChatMemoryRepository;
use Ryra\Core\Contracts\Config\Repository;
use Ryra\Core\Contracts\Container\Container;
use Ryra\Core\Support\Manager;

/**
 * @method array messages(string $conversationId)
 * @method void  add(string $conversationId, \Ryra\Core\AI\Message\Message ...$messages)
 * @method void  clear(string $conversationId)
 */
class ChatMemoryManager extends Manager
{
    protected Repository $config;

    /**
     * @var array<string, Closure>
     */
    protected array $customCreators = [];

    /**
     * @var array<string, ChatMemory>
     */
    protected array $resolved = [];

    public function __construct(protected Container $container)
    {
        $this->config = $this->container->get('config');
    }

    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('ai.memory.default', 'array');
    }

    public function driver(?string $driver = null, array $params = []): mixed
    {
        $driver ??= $this->getDefaultDriver();

        return $this->resolved[$driver] ??= $this->resolve($driver);
    }

    public function extend(string $driver, Closure $creator): static
    {
        $this->customCreators[$driver] = $creator;
        unset($this->resolved[$driver]);

        return $this;
    }

    protected function resolve(string $driver): ChatMemory
    {
        $config = $this->config->get("ai.memory.drivers.{$driver}");

        if (!is_array($config)) {
            throw new AIException(
                sprintf('Chat memory driver "%s" chưa được cấu hình trong config/ai.php.', $driver)
            );
        }

        if (isset($this->customCreators[$driver])) {
            return ($this->customCreators[$driver])($config, $this->container);
        }

        return new MessageWindowChatMemory(
            $this->repository($driver, $config),
            (int) ($config['max_messages'] ?? 20),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function repository(string $driver, array $config): ChatMemoryRepository
    {
        if (isset($config['repository']) && class_exists($config['repository'])) {
            return new $config['repository']($config);
        }

        return match ($driver) {
            'array' => new InMemoryChatMemoryRepository(),
            'database' => new DatabaseChatMemoryRepository(
                $this->container->get('database')->getConnection($config['connection'] ?? null),
                $config,
            ),
            default => throw new AIException(
                sprintf('Chat memory driver "%s" is not supported.', $driver)
            ),
        };
    }
}
