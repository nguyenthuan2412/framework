<?php

namespace Ryra\Core\AI;

use Closure;
use Ryra\Core\AI\Drivers\ClaudeDriver;
use Ryra\Core\AI\Drivers\GeminiDriver;
use Ryra\Core\AI\Drivers\OpenAIDriver;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\Contracts\AI\ChatModel;
use Ryra\Core\Contracts\Config\Repository;
use Ryra\Core\Contracts\Container\Container;
use Ryra\Core\Macro\Macroable;
use Ryra\Core\Support\Manager;

/**
 * Điểm vào của toàn bộ component AI.
 *
 * Method không khai báo ở đây sẽ được Manager chuyển tiếp sang driver mặc định.
 *
 * @method \Ryra\Core\AI\Response\ChatResponse call(Prompt $prompt, ?ChatOptions $options = null)
 * @method \Generator                          stream(Prompt $prompt, ?ChatOptions $options = null)
 */
class AIManager extends Manager
{
    use Macroable;

    /**
     * Tên gọi khác của cùng một driver, quy về tên chuẩn trước khi đọc config.
     */
    protected const ALIASES = [
        'anthropic' => 'claude',
        'google' => 'gemini',
    ];

    protected Repository $config;

    /**
     * Driver do ứng dụng tự đăng ký thêm ngoài các driver có sẵn.
     *
     * @var array<string, Closure>
     */
    protected array $customCreators = [];

    /**
     * @var array<string, ChatModel>
     */
    protected array $resolved = [];

    public function __construct(protected Container $container)
    {
        $this->config = $this->container->get('config');
    }

    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('ai.default', 'claude');
    }

    /**
     * Lấy driver theo tên, có cache để không dựng lại mỗi lần gọi.
     */
    public function driver(?string $driver = null, array $params = []): mixed
    {
        $driver ??= $this->getDefaultDriver();
        $driver = static::ALIASES[$driver] ?? $driver;

        return $this->resolved[$driver] ??= $this->resolve($driver);
    }

    /**
     * Mở một phiên hội thoại mới trên driver chỉ định.
     */
    public function chat(?string $driver = null): ChatClient
    {
        return new ChatClient($this->driver($driver));
    }

    /**
     * Đăng ký thêm nhà cung cấp mới mà không phải sửa core.
     *
     * AI::extend('grok', fn (array $config) => new GrokDriver($config));
     */
    public function extend(string $driver, Closure $creator): static
    {
        $this->customCreators[$driver] = $creator;
        unset($this->resolved[$driver]);

        return $this;
    }

    /**
     * Danh sách driver đã cấu hình trong config/ai.php.
     *
     * @return array<int, string>
     */
    public function drivers(): array
    {
        return array_keys((array) $this->config->get('ai.drivers', []));
    }

    protected function resolve(string $driver): ChatModel
    {
        $config = $this->driverConfig($driver);

        if (isset($this->customCreators[$driver])) {
            return ($this->customCreators[$driver])($config, $this->container);
        }

        // Cho phép chỉ định thẳng class trong config, tiện khi tự viết driver riêng.
        if (isset($config['driver']) && class_exists($config['driver'])) {
            return new $config['driver']($config);
        }

        return match ($driver) {
            'claude' => new ClaudeDriver($config),
            'openai' => new OpenAIDriver($config),
            'gemini' => new GeminiDriver($config),
            default => throw new AIException(
                sprintf('AI driver "%s" is not supported. Dùng AI::extend() để tự đăng ký.', $driver)
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function driverConfig(string $driver): array
    {
        $config = $this->config->get("ai.drivers.{$driver}");

        if (!is_array($config)) {
            throw new AIException(
                sprintf('AI driver "%s" chưa được cấu hình trong config/ai.php.', $driver)
            );
        }

        return $config;
    }
}
