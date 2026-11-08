<?php

namespace Ryra\Core\Messenger\Transport\Database;

use Ryra\Core\Contracts\Database\ConnectionManager;
use Ryra\Core\Messenger\Envelope;
use Ryra\Core\Messenger\Serialization\SerializerInterface;
use Ryra\Core\Messenger\Transport\TransportInterface;

class DatabaseTransport implements TransportInterface
{
    private DatabaseReceiver $receiver;
    private DatabaseSender $sender;

    public function __construct(
        private ConnectionManager $connectionManager,
        private SerializerInterface $serializer
    ) {}

    public function get(array $queues = []): ?Envelope
    {
        return $this->getReceiver()->get($queues);
    }

    public function ack(Envelope $envelope): void
    {
        $this->getReceiver()->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->getReceiver()->reject($envelope);
    }

    public function send(Envelope $envelope): Envelope
    {
        return $this->getSender()->send($envelope);
    }

    private function getReceiver(): DatabaseReceiver
    {
        return $this->receiver ??= new DatabaseReceiver(
            $this->connectionManager,
            $this->serializer
        );
    }

    private function getSender(): DatabaseSender
    {
        return $this->sender ??= new DatabaseSender(
            $this->connectionManager,
            $this->serializer
        );
    }
}