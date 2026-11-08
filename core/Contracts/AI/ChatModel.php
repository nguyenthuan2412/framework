<?php

namespace Ryra\Core\Contracts\AI;

use Ryra\Core\AI\ChatOptions;
use Ryra\Core\AI\Prompt;
use Ryra\Core\AI\Response\ChatResponse;

interface ChatModel
{
    /**
     * Gửi prompt tới nhà cung cấp và nhận về một phản hồi hoàn chỉnh.
     */
    public function call(Prompt $prompt, ?ChatOptions $options = null): ChatResponse;

    /**
     * Tên định danh của driver (claude, openai, gemini...).
     */
    public function name(): string;

    /**
     * Model mặc định của driver khi prompt không chỉ định rõ.
     */
    public function defaultModel(): string;
}
