<?php

namespace Ryra\Core\Messenger\Transport\AMQP;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Connection
{
    private AMQPChannel $channel = null;

    private array $queues = [];

    private array $exchanges = [];

    public function __construct(private AMQPConfig $config)
    {
    }

    public function channel()
    {
        return $this->channel ??= $this->createConnection()->channel();
    }

    public function publishMessage(AMQPMessage $message, string $routingKey = '')
    {
        $this->declareQueue($routingKey, $this->channel);
        $this->channel->basic_publish($message, '', $this->queue);
    }

    private function createConnection(): AMQPStreamConnection
    {
        $host = $this->config->getHost();
        return new AMQPStreamConnection(
            $host['host'],
            $host['port'],
            $host['user'],
            $host['password'],
            $host['vhost'],
        );
    }

    private function declareQueue(string $name, AMQPChannel $channel): void
    {
        if (isset($this->queues[$name])) {
            return;
        }

        $queueConfig = $this->config->getQueueConfig($name);
        $channel->queue_declare($name, ...$queueConfig);
        $this->queues[$name] = true;

        
    }

    private function declareExchange(string $name, AMQPChannel $channel): void
    {
        if (isset($this->exchanges[$name])) {
            return;
        }

        $exchangeConfig = $this->config->getExchangeConfig($name);
        $channel->exchange_declare($name, ...$exchangeConfig);
        $this->exchanges[$name] = true;
    }
}