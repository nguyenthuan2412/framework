<?php

namespace Ryra\Core\AI\Advisor;

use Closure;
use Ryra\Core\AI\Response\ChatResponse;
use Ryra\Core\AI\Response\FinishReason;

/**
 * Chặn trước những nội dung không được phép gửi lên nhà cung cấp.
 *
 * Advisor không gọi $next nghĩa là request dừng tại đây - vừa tránh rò rỉ dữ
 * liệu vừa không tốn tiền cho một lần gọi chắc chắn bị từ chối.
 */
class SafeGuardAdvisor extends BaseAdvisor
{
    /**
     * @param array<int, string> $forbidden Từ khoá cấm, so khớp không phân biệt hoa thường.
     */
    public function __construct(
        protected array $forbidden,
        protected string $message = 'Yêu cầu này chứa nội dung không được phép xử lý.',
    ) {
        // Chặn sớm nhất có thể, trước cả khi tốn công truy vấn kho vector.
        $this->order = 10;
    }

    public function advise(ChatRequest $request, Closure $next): ChatResponse
    {
        $content = mb_strtolower($request->lastUserMessage()?->content ?? '');

        foreach ($this->forbidden as $term) {
            if ($term !== '' && str_contains($content, mb_strtolower($term))) {
                return new ChatResponse(
                    $this->message,
                    finishReason: FinishReason::ContentFilter,
                    provider: 'safeguard',
                );
            }
        }

        return $next($request);
    }
}
