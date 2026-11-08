<?php

namespace Ryra\Core\AI\Response;

enum FinishReason: string
{
    /** Model đã trả lời xong bình thường. */
    case Stop = 'stop';

    /** Chạm trần max_tokens - phản hồi bị cắt giữa chừng. */
    case Length = 'length';

    /** Model yêu cầu gọi tool và đang chờ kết quả. */
    case ToolCalls = 'tool_calls';

    /** Model từ chối trả lời vì lý do an toàn. */
    case Refusal = 'refusal';

    /** Bị bộ lọc nội dung của nhà cung cấp chặn. */
    case ContentFilter = 'content_filter';

    case Unknown = 'unknown';

    /**
     * Chuẩn hoá stop reason của từng nhà cung cấp về một tập chung.
     */
    public static function normalize(?string $reason): self
    {
        return match ($reason) {
            'end_turn', 'stop', 'STOP', 'stop_sequence' => self::Stop,
            'max_tokens', 'length', 'MAX_TOKENS' => self::Length,
            'tool_use', 'tool_calls', 'function_call' => self::ToolCalls,
            'refusal' => self::Refusal,
            'content_filter', 'SAFETY', 'RECITATION', 'PROHIBITED_CONTENT' => self::ContentFilter,
            default => self::Unknown,
        };
    }

    /**
     * Phản hồi bị cắt ngang, không nên coi là kết quả hoàn chỉnh.
     */
    public function isTruncated(): bool
    {
        return $this === self::Length;
    }
}
