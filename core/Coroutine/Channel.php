<?php

namespace Ryra\Core\Coroutine;

class Channel extends \Swoole\Coroutine\Channel
{
    protected bool $closed = false;

    public static function create(int $capacity = 1): static
    {
        return new static($capacity);
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function getLength(): int
    {
        return $this->length();
    }

    public function isAvailable(): bool
    {
        return ! $this->isClosing();
    }

    public function close(): bool
    {
        $this->closed = true;
        return parent::close();
    }

    public function isClosing(): bool
    {
        return $this->closed || $this->errCode === SWOOLE_CHANNEL_CLOSED;
    }

    public function isTimeout(): bool
    {
        return ! $this->closed && $this->errCode === SWOOLE_CHANNEL_TIMEOUT;
    }
}