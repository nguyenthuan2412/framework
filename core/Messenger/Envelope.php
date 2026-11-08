<?php

namespace Ryra\Core\Messenger;

use Ryra\Core\Messenger\Stamp\StampInterface;
use Ryra\Core\Messenger\Stamp\DelayStamp;
use Ryra\Core\Pipeline\Pipeline;
use Ryra\Core\App;
use DateInterval;

class Envelope
{
    private string $connection;

    private string $queue;

    public function __construct(
        private object $body,
        private array $stamps = [],
        private array $middlewares = [],
        private bool $sendable = true
    )
    {
        $this->connection = $connection ?? config('messenger.default');
        $this->queue = config("messenger.connections.{$this->connection}.queue");
    }

    public function getBody(): object
    {
        return $this->body;
    }

    public function delay(int|DateInterval $interval): static
    {
        return $this->addStamp(new DelayStamp($interval));
    }

    public function onConnection(string $connection): static
    {
        $this->connection = $connection;
        return $this;
    }

    public function onQueue(string $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    public function hasStamp(string $stamp): bool
    {
        return isset($this->stamps[$stamp]);
    }

    public function getStamp(string $stamp): mixed
    {
        return $this->stamps[$stamp] ?? null;
    }

    public function getStamps(?string $type = null): array
    {
        if (is_null($type)) {
            return $this->stamps;
        }

        return array_filter($this->stamps, fn (StampInterface $stamp) => $stamp instanceof $type);
    }

    public function withoutStampsOfType(string $type): array
    {
        return array_filter($this->stamps, fn (StampInterface $stamp) => !($stamp instanceof $type));
    }

    public function addStamp(StampInterface $stamp): static
    {
        $this->stamps[get_class($stamp)] = $stamp;
        return $this;
    }

    public function __destruct()
    {
        if ($this->sendable) {
            (new Pipeline())
                ->send($this)
                ->through($this->middlewares)
                ->then(function (Envelope $envelope) {
                    $transport = App::service('messenger.transport')->createTransport($this->connection);
                    $transport->send($envelope);
                });
        }
    }
}