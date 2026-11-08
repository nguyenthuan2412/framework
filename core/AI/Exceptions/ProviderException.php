<?php

namespace Ryra\Core\AI\Exceptions;

class ProviderException extends AIException
{
    public function __construct(
        string $message,
        public readonly string $provider = '',
        public readonly int $status = 0,
        public readonly array $body = [],
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(string $provider, int $status, array $body, string $raw = ''): static
    {
        $message = $body['error']['message']
            ?? $body['error']['status']
            ?? $body['message']
            ?? ($raw !== '' ? $raw : 'Unknown error');

        return new static(
            sprintf('[%s] HTTP %d: %s', $provider, $status, $message),
            $provider,
            $status,
            $body,
        );
    }

    /**
     * Lỗi có thể thử lại: quá tải, rate limit hoặc lỗi phía nhà cung cấp.
     */
    public function isRetryable(): bool
    {
        return $this->status === 408
            || $this->status === 429
            || $this->status >= 500;
    }

    /**
     * Thời gian nhà cung cấp yêu cầu chờ trước khi gọi lại, tính bằng giây.
     *
     * Gemini trả về trong error.details dạng RetryInfo. Chờ đúng khoảng này
     * hiệu quả hơn nhiều so với backoff đoán mò, nhất là khi dùng gói miễn phí
     * có hạn mức theo phút.
     */
    public function retryAfter(): ?float
    {
        foreach ($this->body['error']['details'] ?? [] as $detail) {
            $delay = $detail['retryDelay'] ?? null;

            if (is_string($delay) && preg_match('/^([\d.]+)s$/', $delay, $matches) === 1) {
                return (float) $matches[1];
            }
        }

        return null;
    }
}
