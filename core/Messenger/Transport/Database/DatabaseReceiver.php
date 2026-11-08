<?php

namespace Ryra\Core\Messenger\Transport\Database;

use Ryra\Core\Messenger\Serialization\SerializerInterface;
use Ryra\Core\Messenger\Stamp\TransportMessageIdStamp;
use Ryra\Core\Messenger\Transport\ReceiverInterface;
use Ryra\Core\Contracts\Database\ConnectionManager;
use Ryra\Core\Database\Query\Builder;
use Ryra\Core\Messenger\Envelope;
use Ryra\Core\Database\DB;
use Ryra\Core\Log\Log;

class DatabaseReceiver implements ReceiverInterface
{
    public function __construct(
        private ConnectionManager $connectionManager,
        private SerializerInterface $serializer
    ) {}

    public function get(array $queues = []): ?Envelope
    {
        $query = $this->getTable()->orderBy('available_at', 'asc');
        if (!empty($queues)) {
            $query->whereIn('queue', $queues);
        }

        $data = $query->lockForUpdate()->first();

        if ($data === null) {
            return null;
        }

        return $this->createEnvelopeFromData($data);
    }

    public function ack(Envelope $envelope): void
    {
        $this->getTable()->where('id', $this->getIdFromEnvelope($envelope))->delete();
    }

    public function reject(Envelope $envelope): void
    {
        try {
            DB::beginTransaction();
            $this->getFailedJobTable()->insert([
                'body' => $this->serializer->encode($envelope->getBody()),
                'headers' => $this->serializer->encode($envelope->getStamps()),
                'created_at' => now(),
            ]);
            $this->getTable()->where('id', $this->getIdFromEnvelope($envelope))->delete();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th);
        }
    }

    private function getTable(): Builder
    {
        return $this->connectionManager->getConnection()->query()->from('jobs');
    }

    private function getFailedJobTable(): Builder
    {
        return $this->connectionManager->getConnection()->query()->from('failed_jobs');
    }

    private function getIdFromEnvelope(Envelope $envelope): string
    {
        return $envelope->getStamp(TransportMessageIdStamp::class)->getId();
    }

    private function createEnvelopeFromData(array $data): Envelope
    {
        $body = $this->serializer->decode($data['body']);
        $stamps = !empty($record['headers'])
            ? $this->serializer->decode($record['headers'])
            : [];

        $envelope = new Envelope($body, $stamps, sendable: false);
        return $envelope->addStamp(new TransportMessageIdStamp($data['id']));
    }
}