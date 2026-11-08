<?php

namespace Ryra\Core\Messenger\Transport\AMQP;

use Ryra\Core\Messenger\Serialization\SerializerInterface;
use Ryra\Core\Messenger\Transport\TransportInterface;
use Ryra\Core\Messenger\Envelope;
use PhpAmqpLib\Channel\AMQPChannel;

class AMQPTransport implements TransportInterface
{
    protected Connection $connection;

    protected AMQPChannel $channel;

    protected AMQPReceiver $receiver;

    protected AMQPSender $sender;

    public function __construct(array $config, protected SerializerInterface $serializer)
    {
        $this->connection = new Connection(new AMQPConfig($config));
    }

    public function get(): iterable
    {
        return $this->getReceiver()->get();
    }

    public function ack(Envelope $envelope): void
    {
        return $this->getReceiver()->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        return $this->getReceiver()->reject($envelope);
    }

    public function send(Envelope $envelope): void
    {
        $this->getSender()->send($envelope);
    }

    private function getReceiver(): AmqpReceiver
    {
        return $this->receiver ??= new AmqpReceiver($this->connection, $this->serializer);
    }

    private function getSender(): AmqpSender
    {
        return $this->sender ??= new AmqpSender($this->connection, $this->serializer);
    }
}