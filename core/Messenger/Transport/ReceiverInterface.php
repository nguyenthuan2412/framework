<?php

namespace Ryra\Core\Messenger\Transport;

use Ryra\Core\Messenger\Envelope;

interface ReceiverInterface
{
    /**
     * Get a message from the transport.
     *
     * @param string[] $queues
     * 
     * @return Envelope|null
     */
    public function get(array $queues = []): ?Envelope;

    public function ack(Envelope $envelope): void;

    public function reject(Envelope $envelope): void;
}