<?php

namespace Ryra\Core\AI\Drivers;

use Generator;
use Ryra\Core\AI\ChatOptions;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Http\SseEvent;
use Ryra\Core\AI\Http\Transport;
use Ryra\Core\AI\Prompt;
use Ryra\Core\AI\Response\ChatResponse;
use Ryra\Core\AI\Response\FinishReason;
use Ryra\Core\AI\Response\StreamChunk;
use Ryra\Core\AI\Response\Usage;
use Ryra\Core\Contracts\AI\StreamingChatModel;

abstract class AbstractDriver implements StreamingChatModel
{
    protected Transport $transport;

    protected ChatOptions $defaults;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(protected array $config, ?Transport $transport = null)
    {
        $this->transport = $transport ?? new Transport(
            $this->name(),
            (float) ($config['timeout'] ?? 120),
            (float) ($config['connect_timeout'] ?? 10),
            (int) ($config['retries'] ?? 2),
        );

        $this->defaults = $this->buildDefaults();
    }

    public function call(Prompt $prompt, ?ChatOptions $options = null): ChatResponse
    {
        $options = $this->resolveOptions($options);

        $body = $this->transport->post(
            $this->endpoint($options, false),
            $this->headers($options),
            $this->buildPayload($prompt, $options, false),
        );

        return $this->parseResponse($body);
    }

    public function stream(Prompt $prompt, ?ChatOptions $options = null): Generator
    {
        $options = $this->resolveOptions($options);
        $state = new StreamState();

        $events = $this->transport->stream(
            $this->endpoint($options, true),
            $this->headers($options),
            $this->buildPayload($prompt, $options, true),
        );

        foreach ($events as $event) {
            if ($event->isDone()) {
                break;
            }

            foreach ($this->parseStreamEvent($event, $state) as $chunk) {
                yield $chunk;
            }
        }

        yield StreamChunk::last(
            $state->toolCalls(),
            $state->getUsage(),
            $state->getFinishReason(),
        );
    }

    public function defaultModel(): string
    {
        return (string) ($this->config['model'] ?? '');
    }

    /**
     * Gom phản hồi stream thành một ChatResponse hoàn chỉnh.
     * Dùng khi muốn hiển thị dần cho người dùng nhưng vẫn cần kết quả cuối.
     */
    public function collect(Prompt $prompt, ?ChatOptions $options = null, ?callable $onChunk = null): ChatResponse
    {
        $text = '';
        $last = null;

        foreach ($this->stream($prompt, $options) as $chunk) {
            $text .= $chunk->text;
            $last = $chunk;

            if ($onChunk !== null && $chunk->text !== '') {
                $onChunk($chunk);
            }
        }

        return new ChatResponse(
            $text,
            $last?->toolCalls ?? [],
            $last?->usage ?? new Usage(),
            $last?->finishReason ?? FinishReason::Unknown,
            $options?->getModel() ?? $this->defaultModel(),
            $this->name(),
        );
    }

    /**
     * Options mặc định lấy từ config của driver.
     */
    protected function buildDefaults(): ChatOptions
    {
        return ChatOptions::make()
            ->model($this->config['model'] ?? null)
            ->maxTokens(isset($this->config['max_tokens']) ? (int) $this->config['max_tokens'] : null)
            ->temperature(isset($this->config['temperature']) ? (float) $this->config['temperature'] : null)
            ->effort($this->config['effort'] ?? null)
            ->thinking($this->config['thinking'] ?? null);
    }

    protected function resolveOptions(?ChatOptions $options): ChatOptions
    {
        $options = $options === null ? ChatOptions::make() : clone $options;

        return $options->withDefaults($this->defaults);
    }

    protected function apiKey(): string
    {
        $key = (string) ($this->config['api_key'] ?? '');

        if ($key === '') {
            throw new AIException(
                sprintf('Missing API key for AI driver "%s". Kiểm tra lại config/ai.php và file .env.', $this->name())
            );
        }

        return $key;
    }

    protected function model(ChatOptions $options): string
    {
        $model = $options->getModel() ?? $this->defaultModel();

        if ($model === '') {
            throw new AIException(sprintf('No model configured for AI driver "%s".', $this->name()));
        }

        return $model;
    }

    /**
     * URL đích của một lần gọi.
     */
    abstract protected function endpoint(ChatOptions $options, bool $stream): string;

    /**
     * @return array<string, string>
     */
    abstract protected function headers(ChatOptions $options): array;

    /**
     * @return array<string, mixed>
     */
    abstract protected function buildPayload(Prompt $prompt, ChatOptions $options, bool $stream): array;

    /**
     * @param array<string, mixed> $body
     */
    abstract protected function parseResponse(array $body): ChatResponse;

    /**
     * Một SSE event có thể sinh ra 0 hoặc nhiều chunk.
     *
     * @return iterable<int, StreamChunk>
     */
    abstract protected function parseStreamEvent(SseEvent $event, StreamState $state): iterable;
}
