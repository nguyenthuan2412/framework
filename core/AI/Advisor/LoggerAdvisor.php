<?php

namespace Ryra\Core\AI\Advisor;

use Closure;
use Psr\Log\LoggerInterface;
use Ryra\Core\AI\Response\ChatResponse;
use Throwable;

/**
 * Ghi lại mỗi lần gọi model: model nào, mất bao lâu, tốn bao nhiêu token.
 *
 * Không có số liệu này thì không thể biết tính năng AI đang tốn bao nhiêu tiền
 * và chỗ nào chậm.
 */
class LoggerAdvisor extends BaseAdvisor
{
    public function __construct(
        protected LoggerInterface $logger,
        protected string $level = 'info',
        /** Ghi cả nội dung prompt - cân nhắc vì có thể chứa dữ liệu cá nhân. */
        protected bool $logContent = false,
    ) {
        // Nằm ngoài cùng để đo được cả thời gian của những advisor khác.
        $this->order = 0;
    }

    public function advise(ChatRequest $request, Closure $next): ChatResponse
    {
        $startedAt = microtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->logger->error('AI call failed', [
                'model' => $request->options->getModel(),
                'duration_ms' => $this->elapsed($startedAt),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->logger->log($this->level, 'AI call completed', array_filter([
            'provider' => $response->provider,
            'model' => $response->model,
            'duration_ms' => $this->elapsed($startedAt),
            'finish_reason' => $response->finishReason->value,
            'tool_calls' => count($response->toolCalls) ?: null,
            'usage' => $response->usage->toArray(),
            'messages' => $this->logContent ? $request->prompt->toArray() : null,
            'response' => $this->logContent ? $response->text : null,
        ], static fn (mixed $value): bool => $value !== null));

        return $response;
    }

    protected function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
