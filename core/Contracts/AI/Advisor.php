<?php

namespace Ryra\Core\Contracts\AI;

use Closure;
use Ryra\Core\AI\Advisor\ChatRequest;
use Ryra\Core\AI\Response\ChatResponse;

/**
 * Lớp bọc quanh mỗi lần gọi model.
 *
 * Advisor can thiệp được cả trước khi gửi (sửa prompt, chèn ngữ cảnh, chặn nội
 * dung) lẫn sau khi nhận (ghi log, lưu lịch sử, đo lường), nhờ vậy các mối quan
 * tâm chung không phải lặp lại trong từng chỗ gọi.
 */
interface Advisor
{
    /**
     * Gọi $next($request) để chuyển tiếp cho advisor kế tiếp.
     * Không gọi $next đồng nghĩa chặn hẳn request và tự trả về phản hồi.
     *
     * @param Closure(ChatRequest): ChatResponse $next
     */
    public function advise(ChatRequest $request, Closure $next): ChatResponse;

    /**
     * Số nhỏ chạy trước (nằm ngoài cùng).
     */
    public function order(): int;
}
