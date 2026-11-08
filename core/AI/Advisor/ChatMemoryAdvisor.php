<?php

namespace Ryra\Core\AI\Advisor;

use Closure;
use Ryra\Core\AI\Message\Message;
use Ryra\Core\AI\Message\SystemMessage;
use Ryra\Core\AI\Response\ChatResponse;
use Ryra\Core\Contracts\AI\ChatMemory;

/**
 * Nạp lịch sử hội thoại vào prompt trước khi gửi, và lưu lại lượt mới sau khi
 * nhận phản hồi.
 *
 * Nhờ advisor này, tầng nghiệp vụ chỉ cần gửi câu hỏi mới nhất mà model vẫn nhớ
 * được toàn bộ mạch hội thoại.
 */
class ChatMemoryAdvisor extends BaseAdvisor
{
    public function __construct(
        protected ChatMemory $memory,
        protected string $conversationId,
    ) {
        // Chạy sát model nhất để lịch sử được nạp sau khi các advisor khác đã
        // sửa xong prompt của lượt hiện tại.
        $this->order = 80;
    }

    public function advise(ChatRequest $request, Closure $next): ChatResponse
    {
        $history = $this->memory->messages($this->conversationId);

        // Chỉ lấy phần hội thoại: chỉ dẫn hệ thống đã có sẵn ở lượt hiện tại.
        $history = array_values(array_filter(
            $history,
            static fn (Message $message): bool => !$message instanceof SystemMessage,
        ));

        $turn = $request->prompt->conversation();
        $request->prependHistory($history);

        $response = $next($request);

        $this->memory->add($this->conversationId, ...$turn, ...[$response->toMessage()]);

        return $response;
    }
}
