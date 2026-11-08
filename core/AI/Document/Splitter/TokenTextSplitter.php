<?php

namespace Ryra\Core\AI\Document\Splitter;

/**
 * Cắt theo số token ước lượng thay vì số ký tự.
 *
 * Dùng khi cần khống chế lượng ngữ cảnh nhồi vào prompt, vì giới hạn của model
 * tính bằng token chứ không phải ký tự.
 *
 * Lưu ý: đây là ước lượng theo tỉ lệ ký tự/token, không phải đếm bằng tokenizer
 * thật. Tiếng Việt có dấu nên tốn token hơn tiếng Anh - mặc định 3.5 ký tự một
 * token là mức an toàn cho nội dung tiếng Việt lẫn tiếng Anh. Cần con số chính
 * xác tuyệt đối thì truyền hàm đếm riêng vào CharacterTextSplitter.
 */
class TokenTextSplitter extends CharacterTextSplitter
{
    public function __construct(
        int $chunkTokens = 512,
        int $overlapTokens = 64,
        protected float $charsPerToken = 3.5,
        array $separators = self::DEFAULT_SEPARATORS,
    ) {
        parent::__construct(
            $chunkTokens,
            $overlapTokens,
            $separators,
            fn (string $text): int => $this->estimateTokens($text),
        );
    }

    public function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / $this->charsPerToken);
    }
}
