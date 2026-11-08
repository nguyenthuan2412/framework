<?php

namespace Ryra\Core\AI\Drivers;

use Ryra\Core\AI\ChatOptions;
use Ryra\Core\AI\Http\SseEvent;
use Ryra\Core\AI\Message\AssistantMessage;
use Ryra\Core\AI\Message\Media;
use Ryra\Core\AI\Message\Message;
use Ryra\Core\AI\Message\SystemMessage;
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
 * Driver cho Anthropic Messages API.
 *
 * @see https://platform.claude.com/docs/en/api/messages
 */
class ClaudeDriver extends AbstractDriver
{
    protected const API_VERSION = '2023-06-01';

    protected const DEFAULT_BASE_URL = 'https://api.anthropic.com/v1';

    /**
     * Các dòng model đã bỏ tham số sampling: gửi temperature/top_p sẽ bị trả về 400.
     * Muốn điều chỉnh hành vi thì dùng prompt và effort thay thế.
     */
    protected const NO_SAMPLING_MODELS = [
        'claude-fable-5',
        'claude-mythos-5',
        'claude-opus-5',
        'claude-opus-4-8',
        'claude-opus-4-7',
        'claude-sonnet-5',
    ];

    public function name(): string
    {
        return 'claude';
    }

    protected function endpoint(ChatOptions $options, bool $stream): string
    {
        return $this->baseUrl() . '/messages';
    }

    protected function headers(ChatOptions $options): array
    {
        $headers = [
            'x-api-key' => $this->apiKey(),
            'anthropic-version' => static::API_VERSION,
        ];

        if (!empty($this->config['beta'])) {
            $headers['anthropic-beta'] = implode(',', (array) $this->config['beta']);
        }

        return $headers;
    }

    protected function buildPayload(Prompt $prompt, ChatOptions $options, bool $stream): array
    {
        $model = $this->model($options);

        $payload = [
            'model' => $model,
            // Anthropic bắt buộc phải có max_tokens.
            'max_tokens' => $options->getMaxTokens() ?? 4096,
            'messages' => $this->mapMessages($prompt),
        ];

        if (($system = $this->mapSystem($prompt)) !== []) {
            $payload['system'] = $system;
        }

        if ($stream) {
            $payload['stream'] = true;
        }

        if (!$this->rejectsSampling($model)) {
            if ($options->getTemperature() !== null) {
                $payload['temperature'] = $options->getTemperature();
            }

            if ($options->getTopP() !== null) {
                $payload['top_p'] = $options->getTopP();
            }
        }

        if ($options->getStopSequences() !== null) {
            $payload['stop_sequences'] = $options->getStopSequences();
        }

        if ($options->getThinking() === true) {
            $payload['thinking'] = ['type' => 'adaptive'];
        }

        $outputConfig = [];

        if ($options->getEffort() !== null) {
            $outputConfig['effort'] = $options->getEffort();
        }

        if ($options->isStructured()) {
            $outputConfig['format'] = [
                'type' => 'json_schema',
                'schema' => $options->getResponseSchema(),
            ];
        }

        if ($outputConfig !== []) {
            $payload['output_config'] = $outputConfig;
        }

        if ($options->hasTools()) {
            $payload['tools'] = array_map(
                static fn (ToolDefinition $tool): array => array_filter([
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'input_schema' => $tool->schema(),
                    'strict' => $tool->strict ?: null,
                ], static fn (mixed $value): bool => $value !== null),
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
        $text = '';
        $toolCalls = [];

        foreach ($body['content'] ?? [] as $block) {
            match ($block['type'] ?? '') {
                'text' => $text .= $block['text'] ?? '',
                'tool_use' => $toolCalls[] = new ToolCall(
                    $block['id'] ?? '',
                    $block['name'] ?? '',
                    (array) ($block['input'] ?? []),
                ),
                // Khối thinking không phải nội dung trả lời, bỏ qua.
                default => null,
            };
        }

        return new ChatResponse(
            $text,
            $toolCalls,
            $this->mapUsage($body['usage'] ?? []),
            FinishReason::normalize($body['stop_reason'] ?? null),
            (string) ($body['model'] ?? ''),
            $this->name(),
            $body,
        );
    }

    protected function parseStreamEvent(SseEvent $event, StreamState $state): iterable
    {
        $data = $event->json();

        switch ($data['type'] ?? $event->name) {
            case 'message_start':
                $state->usage($this->mapUsage($data['message']['usage'] ?? []));
                break;

            case 'content_block_start':
                $block = $data['content_block'] ?? [];

                if (($block['type'] ?? '') === 'tool_use') {
                    $state->openToolCall(
                        $data['index'] ?? 0,
                        $block['id'] ?? '',
                        $block['name'] ?? '',
                    );
                }
                break;

            case 'content_block_delta':
                $delta = $data['delta'] ?? [];

                if (($delta['type'] ?? '') === 'text_delta' && ($delta['text'] ?? '') !== '') {
                    $state->appendText($delta['text']);
                    yield StreamChunk::delta($delta['text'], $data);
                }

                if (($delta['type'] ?? '') === 'input_json_delta') {
                    $state->appendToolArguments($data['index'] ?? 0, $delta['partial_json'] ?? '');
                }
                break;

            case 'message_delta':
                if (isset($data['delta']['stop_reason'])) {
                    $state->finishReason(FinishReason::normalize($data['delta']['stop_reason']));
                }

                $state->usage($this->mapUsage($data['usage'] ?? []));
                break;

            case 'error':
                $state->finishReason(FinishReason::Unknown);
                break;
        }
    }

    /**
     * Anthropic tách system prompt ra khỏi luồng hội thoại và cho phép
     * đánh dấu cache từng block - rất đáng dùng vì system prompt thường lặp lại.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function mapSystem(Prompt $prompt): array
    {
        $blocks = [];

        foreach ($prompt->systemMessages() as $message) {
            $block = ['type' => 'text', 'text' => $message->content];

            if ($message->cacheable) {
                $block['cache_control'] = ['type' => 'ephemeral'];
            }

            $blocks[] = $block;
        }

        return $blocks;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function mapMessages(Prompt $prompt): array
    {
        $messages = [];

        foreach ($prompt->conversation() as $message) {
            $mapped = $this->mapMessage($message);

            if ($mapped === null) {
                continue;
            }

            // Anthropic yêu cầu gộp mọi tool_result của cùng một lượt vào một
            // message user duy nhất, nếu tách ra model sẽ dừng gọi tool song song.
            $previous = $messages === [] ? null : array_key_last($messages);

            if (
                $previous !== null
                && $messages[$previous]['role'] === 'user'
                && $mapped['role'] === 'user'
                && $message instanceof ToolResultMessage
            ) {
                $messages[$previous]['content'] = [
                    ...$messages[$previous]['content'],
                    ...$mapped['content'],
                ];
                continue;
            }

            $messages[] = $mapped;
        }

        return $messages;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function mapMessage(Message $message): ?array
    {
        if ($message instanceof SystemMessage) {
            return null;
        }

        if ($message instanceof ToolResultMessage) {
            return [
                'role' => 'user',
                'content' => [array_filter([
                    'type' => 'tool_result',
                    'tool_use_id' => $message->toolCallId,
                    'content' => $message->content,
                    'is_error' => $message->isError ?: null,
                ], static fn (mixed $value): bool => $value !== null)],
            ];
        }

        if ($message instanceof AssistantMessage) {
            $content = [];

            if ($message->content !== '') {
                $content[] = ['type' => 'text', 'text' => $message->content];
            }

            foreach ($message->toolCalls as $call) {
                $content[] = [
                    'type' => 'tool_use',
                    'id' => $call->id,
                    'name' => $call->name,
                    'input' => (object) $call->arguments,
                ];
            }

            return $content === [] ? null : ['role' => 'assistant', 'content' => $content];
        }

        $content = [];

        if ($message instanceof UserMessage) {
            foreach ($message->media as $media) {
                $content[] = $this->mapMedia($media);
            }
        }

        if ($message->content !== '') {
            $content[] = ['type' => 'text', 'text' => $message->content];
        }

        return $content === [] ? null : ['role' => 'user', 'content' => $content];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapMedia(Media $media): array
    {
        return [
            'type' => $media->isImage() ? 'image' : 'document',
            'source' => [
                'type' => 'base64',
                'media_type' => $media->mimeType,
                'data' => $media->data,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function mapToolChoice(?string $choice): ?array
    {
        return match ($choice) {
            null, 'auto' => null,
            'any', 'required' => ['type' => 'any'],
            'none' => ['type' => 'none'],
            default => ['type' => 'tool', 'name' => $choice],
        };
    }

    /**
     * @param array<string, mixed> $usage
     */
    protected function mapUsage(array $usage): Usage
    {
        return new Usage(
            (int) ($usage['input_tokens'] ?? 0),
            (int) ($usage['output_tokens'] ?? 0),
            (int) ($usage['cache_read_input_tokens'] ?? 0),
            (int) ($usage['cache_creation_input_tokens'] ?? 0),
        );
    }

    protected function rejectsSampling(string $model): bool
    {
        foreach (static::NO_SAMPLING_MODELS as $prefix) {
            if (str_starts_with($model, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? static::DEFAULT_BASE_URL), '/');
    }
}
