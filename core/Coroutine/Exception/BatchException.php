<?php

namespace Ryra\Core\Coroutine\Exception;

class BatchException extends RuntimeException
{
    /**
     * @param array<int|string, \Throwable> $exceptions Các exception từ coroutine con, key tương ứng với key task
     * @param array<int|string, mixed>      $results    Kết quả partial (task thành công vẫn có giá trị)
     */
    public function __construct(
        private readonly array $exceptions,
        private readonly array $results = [],
    ) {
        $count = count($exceptions);
        $keys  = implode(', ', array_keys($exceptions));
        parent::__construct("$count coroutine(s) failed in batch (keys: $keys).");
    }

    /**
     * Lấy tất cả exceptions từ các coroutine con.
     *
     * @return array<int|string, \Throwable>
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    /**
     * Lấy exception của 1 task theo key.
     */
    public function getException(int|string $key): ?\Throwable
    {
        return $this->exceptions[$key] ?? null;
    }

    /**
     * Kết quả partial — task thành công vẫn có giá trị, task lỗi là null.
     *
     * @return array<int|string, mixed>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Kiểm tra key cụ thể có bị lỗi không.
     */
    public function hasException(int|string $key): bool
    {
        return isset($this->exceptions[$key]);
    }
}
