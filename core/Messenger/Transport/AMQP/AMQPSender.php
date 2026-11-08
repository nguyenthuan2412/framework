<?php

namespace Ryra\Core\Messenger\Transport\AMQP;

use Ryra\Core\Messenger\Serialization\SerializerInterface;
use Ryra\Core\Messenger\Transport\SenderInterface;
use Ryra\Core\Messenger\Envelope;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Ryra\Core\Messenger\Stamp\DelayStamp;

class AMQPSender implements SenderInterface
{
    public function __construct(private Connection $connection, private SerializerInterface $serializer)
    {
    }

    public function send(Envelope $envelope): void
    {
        $body = $this->serializer->encode($envelope->getBody());

        $msg = new AMQPMessage($body);

        if (!empty($delay = $envelope->getStamp(DelayStamp::class))) {
            $msg->set('application_headers', new AMQPTable([
                'x-delay' => $delay->getDelay(),
            ]));
        }

        $this->connection->publishMessage($msg, $envelope->queue);
    }
}