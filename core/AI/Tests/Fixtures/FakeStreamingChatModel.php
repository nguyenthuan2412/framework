<?php

namespace Ryra\Core\AI\Tests\Fixtures;

use Generator;
use Ryra\Core\AI\ChatOptions;
use Ryra\Core\AI\Prompt;
use Ryra\Core\AI\Response\ChatResponse;
use Ryra\Core\AI\Response\FinishReason;
use Ryra\Core\AI\Response\StreamChunk;
use Ryra\Core\AI\Response\Usage;
use Ryra\Core\Contracts\AI\StreamingChatModel;

/**
 * Model giả có streaming: nhả từng mảnh của câu trả lời đã dựng sẵn.
 */
class FakeStreamingChatModel implements StreamingChatModel
{
    /**
     * @var array<int, Prompt>
     */
    public array $received = [];

    /**
     * @param array<int, string> $chunks
     */
    public function __construct(
        protected array $chunks = ['Xin ', 'chào'],
        protected Usage $usage = new Usage(5, 3),
    ) {}

    public function call(Prompt $prompt, ?ChatOptions $options = null): ChatResponse
    {
        $this->received[] = $prompt->clone();

        return new ChatResponse(implode('', $this->chunks), usage: $this->usage, provider: $this->name());
    }

    public function stream(Prompt $prompt, ?ChatOptions $options = null): Generator
    {
        $this->received[] = $prompt->clone();

        foreach ($this->chunks as $chunk) {
            yield StreamChunk::delta($chunk);
        }

        yield StreamChunk::last([], $this->usage, FinishReason::Stop);
    }

    public function name(): string
    {
        return 'fake-stream';
    }

    public function defaultModel(): string
    {
        return 'fake-stream-model';
    }
}
