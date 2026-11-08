<?php

namespace Ryra\Core\Messenger\Transport;

use Ryra\Core\Messenger\Transport\Database\DatabaseTransport;
use Ryra\Core\Messenger\Serialization\SerializerInterface;
use Ryra\Core\Messenger\Transport\AMQP\AMQPTransport;
use Ryra\Core\Contracts\Container\Container;
use Ryra\Core\Support\Str;

class TransportFactory
{
    public function __construct(
        protected Container $container,
        protected SerializerInterface $serializer
    ) {}

    public function createTransport(string $connection): TransportInterface
    {
        $config = config("messenger.connections.{$connection}");
        $driver = Str::studly($config['driver']);
        $method = "create{$driver}Transport";
        return $this->{$method}($config, $this->serializer);
    }

    protected function createAmqpTransport(array &$config, SerializerInterface $serializer): AMQPTransport
    {
        return $this->container->make(AMQPTransport::class, [
            'config' => $config,
            'serializer' => $serializer,
        ]);
    }

    protected function createDatabaseTransport(array &$config, SerializerInterface $serializer): DatabaseTransport
    {
        return $this->container->make(DatabaseTransport::class, [
            'config' => $config,
            'serializer' => $serializer,
        ]);
    }
}