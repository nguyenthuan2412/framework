<?php

namespace Ryra\Core\Contracts\AI;

use Generator;
use Ryra\Core\AI\ChatOptions;
use Ryra\Core\AI\Prompt;
use Ryra\Core\AI\Response\StreamChunk;

interface StreamingChatModel extends ChatModel
{
    /**
     * Gửi prompt và nhận về từng mảnh phản hồi theo thời gian thực.
     *
     * @return Generator<int, StreamChunk>
     */
    public function stream(Prompt $prompt, ?ChatOptions $options = null): Generator;
}
