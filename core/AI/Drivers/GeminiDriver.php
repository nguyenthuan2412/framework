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
use Ryra\Core\Support\Str;
use stdClass;

/**
 * Driver cho Google Gemini API.
 *
 * @see https://ai.google.dev/api/generate-content
 */
class GeminiDriver extends AbstractDriver
{
    protected const DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * Gemini chỉ nhận một tập con của JSON Schema, gửi thừa khoá sẽ bị 400.
     */
    protected const SCHEMA_ALLOWED_KEYS = [
        'type', 'format', 'description', 'nullable', 'enum',
        'items', 'properties', 'required', 'propertyOrdering',
    ];

    public function name(): string
    {
        return 'gemini';
    }

    protected function endpoint(ChatOptions $options, bool $stream): string
    {
        $method = $stream ? 'streamGenerateContent' : 'generateContent';
        $url = sprintf('%s/models/%s:%s', $this->baseUrl(), $this->model($options), $method);

        // alt=sse để Gemini trả về SSE thay vì mảng JSON gộp.
        return $stream ? $url . '?alt=sse' : $url;
    }

    protected function headers(ChatOptions $options): array
    {
        return ['x-goog-api-key' => $this->apiKey()];
    }

    protected function buildPayload(Prompt $prompt, ChatOptions $options, bool $stream): array
    {
        $payload = ['contents' => $this->mapContents($prompt)];

        if (($system = $this->mapSystem($prompt)) !== null) {
            $payload['systemInstruction'] = $system;
        }

        $generationConfig = array_filter([
            'maxOutputTokens' => $options->getMaxTokens(),
            'temperature' => $options->getTemperature(),
            'topP' => $options->getTopP(),
            'stopSequences' => $options->getStopSequences(),
        ], static fn (mixed $value): bool => $value !== null);

        if ($options->isStructured()) {
            $generationConfig['responseMimeType'] = 'application/json';
            $generationConfig['responseSchema'] = $this->sanitizeSchema($options->getResponseSchema());
        }

        // Dòng Gemini 3 mặc định bật suy nghĩ, và phần suy nghĩ ăn chung hạn mức
        // maxOutputTokens. Không khống chế thì câu trả lời dễ bị cắt cụt.
        if (($thinkingLevel = $this->thinkingLevel($options)) !== null) {
            $generationConfig['thinkingConfig'] = ['thinkingLevel' => $thinkingLevel];
        }

        if ($generationConfig !== []) {
            $payload['generationConfig'] = $generationConfig;
        }

        if ($options->hasTools()) {
            $payload['tools'] = [[
                'functionDeclarations' => array_map(
                    fn (ToolDefinition $tool): array => [
                        'name' => $tool->name,
                        'description' => $tool->description,
                        'parameters' => $this->sanitizeSchema($tool->schema()),
                    ],
                    $options->getTools()->all(),
                ),
            ]];

            if (($mode = $this->mapToolChoice($options->getToolChoice())) !== null) {
                $payload['toolConfig'] = ['functionCallingConfig' => $mode];
            }
        }

        return array_merge($payload, $options->getExtra());
    }

    protected function parseResponse(array $body): ChatResponse
    {
        $candidate = $body['candidates'][0] ?? [];
        $text = '';
        $toolCalls = [];

        foreach ($candidate['content']['parts'] ?? [] as $index => $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }

            if (isset($part['functionCall'])) {
                $toolCalls[] = new ToolCall(
                    // Gemini không cấp id cho lời gọi tool, tự sinh để khớp khi trả kết quả.
                    $this->toolCallId($part['functionCall']['name'] ?? '', $index),
                    $part['functionCall']['name'] ?? '',
                    (array) ($part['functionCall']['args'] ?? []),
                    $this->signatureOf($part),
                );
            }
        }

        return new ChatResponse(
            $text,
            $toolCalls,
            $this->mapUsage($body['usageMetadata'] ?? []),
            FinishReason::normalize($candidate['finishReason'] ?? null),
            (string) ($body['modelVersion'] ?? ''),
            $this->name(),
            $body,
        );
    }

    protected function parseStreamEvent(SseEvent $event, StreamState $state): iterable
    {
        $data = $event->json();

        if (isset($data['usageMetadata'])) {
            $state->usage($this->mapUsage($data['usageMetadata']));
        }

        $candidate = $data['candidates'][0] ?? [];

        if (!empty($candidate['finishReason'])) {
            $state->finishReason(FinishReason::normalize($candidate['finishReason']));
        }

        foreach ($candidate['content']['parts'] ?? [] as $index => $part) {
            if (($part['text'] ?? '') !== '') {
                $state->appendText($part['text']);
                yield StreamChunk::delta($part['text'], $data);
            }

            if (isset($part['functionCall'])) {
                $name = $part['functionCall']['name'] ?? '';
                $key = $name . ':' . $index;

                $state->openToolCall($key, $this->toolCallId($name, $index), $name, $this->signatureOf($part));
                $state->appendToolArguments(
                    $key,
                    (string) json_encode($part['functionCall']['args'] ?? [], JSON_UNESCAPED_UNICODE),
                );
            }
        }
    }

    /**
     * Quy mức effort chung của lib về thang thinkingLevel của Gemini.
     *
     * Gemini không có mức "tắt hẳn"; thấp nhất là "minimal".
     */
    protected function thinkingLevel(ChatOptions $options): ?string
    {
        if ($options->getThinking() === false) {
            return 'minimal';
        }

        return match ($options->getEffort()) {
            null => null,
            'xhigh', 'max' => 'high',
            default => $options->getEffort(),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function mapSystem(Prompt $prompt): ?array
    {
        $parts = [];

        foreach ($prompt->systemMessages() as $message) {
            $parts[] = ['text' => $message->content];
        }

        return $parts === [] ? null : ['parts' => $parts];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function mapContents(Prompt $prompt): array
    {
        $contents = [];

        foreach ($prompt->conversation() as $message) {
            $mapped = $this->mapMessage($message);

            if ($mapped === null) {
                continue;
            }

            // Gemini gom nhiều functionResponse của cùng một lượt vào một content.
            $previous = $contents === [] ? null : array_key_last($contents);

            if (
                $previous !== null
                && $contents[$previous]['role'] === $mapped['role']
                && $message instanceof ToolResultMessage
            ) {
                $contents[$previous]['parts'] = [...$contents[$previous]['parts'], ...$mapped['parts']];
                continue;
            }

            $contents[] = $mapped;
        }

        return $contents;
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
                'parts' => [[
                    'functionResponse' => [
                        'name' => $message->name,
                        'response' => ['result' => $message->content],
                    ],
                ]],
            ];
        }

        if ($message instanceof AssistantMessage) {
            $parts = [];

            if ($message->content !== '') {
                $parts[] = ['text' => $message->content];
            }

            foreach ($message->toolCalls as $call) {
                // Dòng Gemini 3 bắt buộc gửi trả thoughtSignature nhận được ở
                // lượt trước, thiếu là bị từ chối với lỗi 400.
                $parts[] = array_filter([
                    'functionCall' => [
                        'name' => $call->name,
                        'args' => (object) $call->arguments,
                    ],
                    'thoughtSignature' => $call->metadata['thought_signature'] ?? null,
                ], static fn (mixed $value): bool => $value !== null);
            }

            return $parts === [] ? null : ['role' => 'model', 'parts' => $parts];
        }

        $parts = [];

        if ($message instanceof UserMessage) {
            foreach ($message->media as $media) {
                $parts[] = $this->mapMedia($media);
            }
        }

        if ($message->content !== '') {
            $parts[] = ['text' => $message->content];
        }

        return $parts === [] ? null : ['role' => 'user', 'parts' => $parts];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapMedia(Media $media): array
    {
        return [
            'inline_data' => [
                'mime_type' => $media->mimeType,
                'data' => $media->data,
            ],
        ];
    }

    /**
     * @return array<string, string>|null
     */
    protected function mapToolChoice(?string $choice): ?array
    {
        return match ($choice) {
            null, 'auto' => null,
            'any', 'required' => ['mode' => 'ANY'],
            'none' => ['mode' => 'NONE'],
            default => ['mode' => 'ANY', 'allowedFunctionNames' => [$choice]],
        };
    }

    /**
     * Lọc bỏ các khoá JSON Schema mà Gemini không hiểu và đổi type sang chữ hoa.
     *
     * @param  array<string, mixed>|null $schema
     * @return array<string, mixed>
     */
    protected function sanitizeSchema(?array $schema): array
    {
        if ($schema === null) {
            return [];
        }

        $clean = [];

        foreach ($schema as $key => $value) {
            if (!in_array($key, static::SCHEMA_ALLOWED_KEYS, true)) {
                continue;
            }

            $clean[$key] = match ($key) {
                'type' => strtoupper((string) $value),
                'items' => $this->sanitizeSchema((array) $value),
                'properties' => $this->sanitizeProperties((array) $value),
                default => $value,
            };
        }

        return $clean;
    }

    /**
     * Giữ properties luôn ở dạng object: tool không tham số mà gửi mảng rỗng
     * sẽ bị Gemini trả về "Cannot bind a list to map for field 'properties'".
     *
     * @param  array<string, mixed> $properties
     * @return array<string, mixed>|stdClass
     */
    protected function sanitizeProperties(array $properties): array|stdClass
    {
        if ($properties === []) {
            return new stdClass();
        }

        return array_map(
            fn (mixed $property): array => $this->sanitizeSchema((array) $property),
            $properties,
        );
    }

    /**
     * @param array<string, mixed> $usage
     */
    protected function mapUsage(array $usage): Usage
    {
        $cached = (int) ($usage['cachedContentTokenCount'] ?? 0);

        return new Usage(
            max(0, (int) ($usage['promptTokenCount'] ?? 0) - $cached),
            (int) ($usage['candidatesTokenCount'] ?? 0),
            $cached,
        );
    }

    protected function toolCallId(string $name, int|string $index): string
    {
        return sprintf('%s_%s', Str::snake($name === '' ? 'tool' : $name), $index);
    }

    /**
     * Chữ ký suy nghĩ đi kèm part, phải giữ nguyên để gửi trả ở lượt sau.
     *
     * @param  array<string, mixed> $part
     * @return array<string, mixed>
     */
    protected function signatureOf(array $part): array
    {
        $signature = $part['thoughtSignature'] ?? null;

        return $signature === null ? [] : ['thought_signature' => $signature];
    }

    protected function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? static::DEFAULT_BASE_URL), '/');
    }
}
