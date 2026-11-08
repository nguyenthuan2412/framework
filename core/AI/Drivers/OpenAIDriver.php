<?php

namespace Ryra\Core\AI\Drivers;

use Ryra\Core\AI\ChatOptions;
use Ryra\Core\AI\Http\SseEvent;
use Ryra\Core\AI\Message\AssistantMessage;
use Ryra\Core\AI\Message\Media;
use Ryra\Core\AI\Message\Message;
use Ryra\Core\AI\Message\ToolResultMessage;
use Ryra\Core\AI\Message\UserMessage;
use Ryra\Core\AI\Prompt;
use Ryra\Core\AI\Response\ChatResponse;
use Ryra\Core\AI\Response\FinishReason;
use Ryra\Core\AI\Response\StreamChunk;
use Ryra\Core\AI\Response\ToolCall;
use Ryra\Core\AI\Response\Usage;
use Ryra\Core\AI\Tool\ToolDefinition;

/**
 * Driver cho OpenAI Chat Completions API.
 *
 * Dùng được với mọi dịch vụ nói cùng giao thức (Azure OpenAI, Groq, Together,
 * OpenRouter, Ollama...) bằng cách đổi base_url trong config.
 *
 * @see https://platform.openai.com/docs/api-reference/chat
 */
class OpenAIDriver extends AbstractDriver
{
    protected const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

    public function name(): string
    {
        return 'openai';
    }

    protected function endpoint(ChatOptions $options, bool $stream): string
    {
        return $this->baseUrl() . '/chat/completions';
    }

    protected function headers(ChatOptions $options): array
    {
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey()];

        if (!empty($this->config['organization'])) {
            $headers['OpenAI-Organization'] = (string) $this->config['organization'];
        }

        return $headers;
    }

    protected function buildPayload(Prompt $prompt, ChatOptions $options, bool $stream): array
    {
        $payload = [
            'model' => $this->model($options),
            'messages' => $this->mapMessages($prompt),
        ];

        if ($options->getMaxTokens() !== null) {
            // Các model đời mới dùng max_completion_tokens; max_tokens đã deprecated.
            $payload['max_completion_tokens'] = $options->getMaxTokens();
        }

        if ($options->getTemperature() !== null) {
            $payload['temperature'] = $options->getTemperature();
        }

        if ($options->getTopP() !== null) {
            $payload['top_p'] = $options->getTopP();
        }

        if ($options->getStopSequences() !== null) {
            $payload['stop'] = $options->getStopSequences();
        }

        if ($stream) {
            $payload['stream'] = true;
            // Không bật cờ này thì chunk cuối không kèm usage.
            $payload['stream_options'] = ['include_usage' => true];
        }

        if ($options->isStructured()) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $options->getResponseSchemaName(),
                    'schema' => $options->getResponseSchema(),
                    'strict' => true,
                ],
            ];
        }

        if ($options->hasTools()) {
            $payload['tools'] = array_map(
                static fn (ToolDefinition $tool): array => [
                    'type' => 'function',
                    'function' => array_filter([
                        'name' => $tool->name,
                        'description' => $tool->description,
                        'parameters' => $tool->schema(),
                        'strict' => $tool->strict ?: null,
                    ], static fn (mixed $value): bool => $value !== null),
                ],
                $options->getTools()->all(),
            );

            if (($choice = $this->mapToolChoice($options->getToolChoice())) !== null) {
                $payload['tool_choice'] = $choice;
            }
        }

        return array_merge($payload, $options->getExtra());
    }

    protected function parseResponse(array $body): ChatResponse
    {
        $choice = $body['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        $toolCalls = [];

        foreach ($message['tool_calls'] ?? [] as $call) {
            $arguments = json_decode($call['function']['arguments'] ?? '{}', true);

            $toolCalls[] = new ToolCall(
                $call['id'] ?? '',
                $call['function']['name'] ?? '',
                is_array($arguments) ? $arguments : [],
            );
        }

        return new ChatResponse(
            (string) ($message['content'] ?? ''),
            $toolCalls,
            $this->mapUsage($body['usage'] ?? []),
            FinishReason::normalize($choice['finish_reason'] ?? null),
            (string) ($body['model'] ?? ''),
            $this->name(),
            $body,
        );
    }

    protected function parseStreamEvent(SseEvent $event, StreamState $state): iterable
    {
        $data = $event->json();

        if (isset($data['usage']) && is_array($data['usage'])) {
            $state->usage($this->mapUsage($data['usage']));
        }

        $choice = $data['choices'][0] ?? null;

        if ($choice === null) {
            return;
        }

        if (!empty($choice['finish_reason'])) {
            $state->finishReason(FinishReason::normalize($choice['finish_reason']));
        }

        $delta = $choice['delta'] ?? [];

        if (($delta['content'] ?? '') !== '' && $delta['content'] !== null) {
            $state->appendText($delta['content']);
            yield StreamChunk::delta($delta['content'], $data);
        }

        foreach ($delta['tool_calls'] ?? [] as $call) {
            $index = $call['index'] ?? 0;

            $state->fillToolCall($index, $call['id'] ?? null, $call['function']['name'] ?? null);

            if (isset($call['function']['arguments'])) {
                $state->appendToolArguments($index, $call['function']['arguments']);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function mapMessages(Prompt $prompt): array
    {
        $messages = [];

        foreach ($prompt->messages() as $message) {
            $mapped = $this->mapMessage($message);

            if ($mapped !== null) {
                $messages[] = $mapped;
            }
        }

        return $messages;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function mapMessage(Message $message): ?array
    {
        if ($message instanceof ToolResultMessage) {
            return [
                'role' => 'tool',
                'tool_call_id' => $message->toolCallId,
                'content' => $message->content,
            ];
        }

        if ($message instanceof AssistantMessage) {
            $mapped = ['role' => 'assistant', 'content' => $message->content];

            if ($message->hasToolCalls()) {
                $mapped['tool_calls'] = array_map(
                    static fn (ToolCall $call): array => [
                        'id' => $call->id,
                        'type' => 'function',
                        'function' => [
                            'name' => $call->name,
                            'arguments' => json_encode($call->arguments, JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                    $message->toolCalls,
                );
            }

            return $mapped;
        }

        if ($message instanceof UserMessage && $message->hasMedia()) {
            $content = [['type' => 'text', 'text' => $message->content]];

            foreach ($message->media as $media) {
                $content[] = $this->mapMedia($media);
            }

            return ['role' => 'user', 'content' => $content];
        }

        return ['role' => $message->role()->value, 'content' => $message->content];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapMedia(Media $media): array
    {
        return [
            'type' => 'image_url',
            'image_url' => ['url' => sprintf('data:%s;base64,%s', $media->mimeType, $media->data)],
        ];
    }

    protected function mapToolChoice(?string $choice): string|array|null
    {
        return match ($choice) {
            null => null,
            'auto' => 'auto',
            'any', 'required' => 'required',
            'none' => 'none',
            default => ['type' => 'function', 'function' => ['name' => $choice]],
        };
    }

    /**
     * @param array<string, mixed> $usage
     */
    protected function mapUsage(array $usage): Usage
    {
        $cached = (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0);

        return new Usage(
            // prompt_tokens đã bao gồm cả phần đọc từ cache, trừ ra để không đếm hai lần.
            max(0, (int) ($usage['prompt_tokens'] ?? 0) - $cached),
            (int) ($usage['completion_tokens'] ?? 0),
            $cached,
        );
    }

    protected function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? static::DEFAULT_BASE_URL), '/');
    }
}
