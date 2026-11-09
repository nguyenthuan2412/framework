<?php

namespace Ryra\Core\Grpc\Server;

use Ryra\Core\Grpc\Metadata\MethodMetadata;
use Ryra\Core\Grpc\Metadata\ServiceMetadata;

/**
 * Thông tin của một lời gọi rpc: metadata (header) client gửi lên, deadline,
 * và nơi đặt header/trailer trả về. Khai báo tham số kiểu ServerContext trong
 * method của service để nhận object này.
 */
final class ServerContext
{
    private array $responseHeaders = [];

    private array $trailers = [];

    private array $attributes = [];

    /**
     * @param array<string, string> $metadata Header client gửi (key viết thường).
     */
    public function __construct(
        public readonly ServiceMetadata $service,
        public readonly MethodMetadata $method,
        private readonly array $metadata,
        public readonly ?string $peer = null,
        public readonly ?float $deadline = null,
    ) {}

    public static function parseTimeout(?string $value): ?float
    {
        if ($value === null || !\preg_match('/^(\d{1,8})([HMSmun])$/', $value, $m)) {
            return null;
        }
        $seconds = (int) $m[1] * match ($m[2]) {
            'H' => 3600,
            'M' => 60,
            'S' => 1,
            'm' => 1e-3,
            'u' => 1e-6,
            'n' => 1e-9,
        };
        return \microtime(true) + $seconds;
    }

    /**
     * Tên đầy đủ của rpc, vd: /user.v1.UserService/GetUser
     */
    public function fullMethod(): string
    {
        return $this->service->path($this->method);
    }

    public function metadata(?string $key = null, ?string $default = null): array|string|null
    {
        if ($key === null) {
            return $this->metadata;
        }
        return $this->metadata[\strtolower($key)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $authorization = $this->metadata('authorization');
        if ($authorization !== null && \stripos($authorization, 'bearer ') === 0) {
            return \substr($authorization, 7);
        }
        return null;
    }

    public function timeRemaining(): ?float
    {
        return $this->deadline === null ? null : \max(0.0, $this->deadline - \microtime(true));
    }

    public function isExpired(): bool
    {
        return $this->deadline !== null && \microtime(true) >= $this->deadline;
    }

    public function setHeader(string $key, string $value): void
    {
        $this->responseHeaders[\strtolower($key)] = $value;
    }

    public function responseHeaders(): array
    {
        return $this->responseHeaders;
    }

    public function setTrailer(string $key, string $value): void
    {
        $this->trailers[\strtolower($key)] = $value;
    }

    public function trailers(): array
    {
        return $this->trailers;
    }

    /**
     * Lưu dữ liệu tuỳ ý (vd interceptor xác thực lưu user hiện tại).
     */
    public function set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
