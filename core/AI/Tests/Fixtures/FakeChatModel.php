<?php

namespace Ryra\Core\AI\Tests\Fixtures;

use Ryra\Core\AI\ChatOptions;
use Ryra\Core\AI\Prompt;
use Ryra\Core\AI\Response\ChatResponse;
use Ryra\Core\Contracts\AI\ChatModel;

/**
 * Model giả trả về lần lượt các phản hồi đã dựng sẵn, dùng để kiểm tra
 * vòng lặp tool mà không cần gọi ra ngoài mạng.
 */
class FakeChatModel implements ChatModel
{
    /**
     * @var array<int, Prompt> Prompt của từng lượt gọi, để test kiểm tra lại.
     */
    public array $received = [];

    /**
     * @param array<int, ChatResponse> $responses
     */
    public function __construct(protected array $responses = [])
    {
    }

    public function call(Prompt $prompt, ?ChatOptions $options = null): ChatResponse
    {
        $this->received[] = $prompt->clone();

        return array_shift($this->responses) ?? new ChatResponse('hết phản hồi dựng sẵn');
    }

    public function name(): string
    {
        return 'fake';
    }

    public function defaultModel(): string
    {
        return 'fake-model';
    }
}
