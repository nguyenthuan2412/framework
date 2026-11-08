<?php

namespace Ryra\Core\Messenger\Transport\Database;

use Ryra\Core\Messenger\Transport\SenderInterface;
use Ryra\Core\Contracts\Database\ConnectionManager;
use Ryra\Core\Messenger\Envelope;
use Ryra\Core\Messenger\Serialization\SerializerInterface;
use Ryra\Core\Messenger\Stamp\DelayStamp;
use Ryra\Core\Messenger\Stamp\NonSendableStampInterface;
use Ryra\Core\Messenger\Stamp\TransportMessageIdStamp;

class DatabaseSender implements SenderInterface
{
    public function __construct(
        private ConnectionManager $connectionManager,
        private SerializerInterface $serializer
    ) {}

    public function send(Envelope $envelope): Envelope
    {
        $stamps = $envelope->withoutStampsOfType(NonSendableStampInterface::class);

        $interval = 0;
        if (!empty($delayStamp = $envelope->getStamp(DelayStamp::class))) {
            $interval = $delayStamp->getDelay();
        }

        $id = $this->connectionManager->getConnection()->query()
            ->table('jobs')
            ->insertGetId([
                'queue' => 'default',
                'body' => $this->serializer->encode($envelope->getBody()),
                'headers' => !empty($stamps) ? $this->serializer->encode($stamps) : null,
                'created_at' => now(),
                'available_at' => now()->addSeconds($interval),
            ]);

        return $envelope->addStamp(new TransportMessageIdStamp($id));
    }
}