<?php

namespace Ryra\Core\Http;

use Swoole\Http\Response as SwooleResponse;

class StreamedResponse extends Response
{
    protected ?\Closure $callback = null;
    protected bool $streamed = false;

    private bool $headersSent = false;

    /**
     * @param int $status The HTTP status code (200 "OK" by default)
     */
    public function __construct(?callable $callback = null, int $status = 200, array $headers = [])
    {
        parent::__construct(null, $status, $headers);

        if (null !== $callback) {
            $this->setCallback($callback);
        }
        $this->streamed = false;
        $this->headersSent = false;
    }

    /**
     * Sets the PHP callback associated with this Response.
     */
    public function setCallback(callable $callback): static
    {
        $this->callback = $callback(...);

        return $this;
    }

    public function getCallback(): ?\Closure
    {
        if (!isset($this->callback)) {
            return null;
        }

        return ($this->callback)(...);
    }

    /**
     * This method only sends the headers once.
     */
    public function sendHeaders(SwooleResponse $swooleResponse, ?int $statusCode = null): static
    {
        if ($this->headersSent) {
            return $this;
        }

        if ($statusCode < 100 || $statusCode >= 200) {
            $this->headersSent = true;
        }

        return parent::sendHeaders($swooleResponse, $statusCode);
    }

    /**
     * This method only sends the content once.
     */
    public function sendContent(SwooleResponse $swooleResponse): static
    {
        if ($this->streamed) {
            return $this;
        }
        $this->streamed = true;

        $callback = $this->callback;
        if (!isset($callback)) {
            throw new \LogicException('The Response callback must be set.');
        }

        foreach ($callback() as $buffer) {
            $swooleResponse->write($buffer);
        }
        $swooleResponse->end();

        return $this;
    }

    /**
     * @return $this
     *
     * @throws \LogicException when the content is not null
     */
    public function setContent(?string $content): static
    {
        if (null !== $content) {
            throw new \LogicException('The content cannot be set on a StreamedResponse instance.');
        }

        $this->streamed = true;

        return $this;
    }

    public function getContent(): string|false
    {
        return false;
    }
}