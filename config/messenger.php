<?php

use Ryra\Core\Messenger\Serialization\PhpSerializer;
use PhpAmqpLib\Wire\AMQPTable;

return [
    'default' => 'database',

    'connections' => [
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION', 'mysql'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'amqp' => [
            'driver' => 'amqp',
            'queue' => env('AMQP_QUEUE', null),

            'hosts' => [
                [
                    'host' => env('AMQP_HOST', '127.0.0.1'),
                    'port' => env('AMQP_PORT', 5672),
                    'user' => env('AMQP_USER', 'guest'),
                    'password' => env('AMQP_PASSWORD', 'guest'),
                    'vhost' => env('AMQP_VHOST', '/'),
                ],
            ],

            'exchanges' => [
                'delayed_exchange' => [
                    'type' => 'x-delayed-message',
                    'durable' => true,
                    'auto_delete' => false,
                    'arguments' => new AMQPTable([
                        'x-delayed-type' => 'direct',
                    ]),
                ]
            ],

            'queues' => [
                'booking.auto_cancel' => [
                    'durable' => true,
                    'exclusive' => false,
                    'auto_delete' => false,
                    'arguments' => [
                        'x-message-ttl' => 60000,
                        'x-dead-letter-exchange' => 'dlx',
                    ],
                    'exchange' => 'delayed_exchange',
                ],

                'booking.sync' => [
                    'durable' => true,
                    'exclusive' => false,
                    'auto_delete' => false,
                    'arguments' => [
                        'x-message-ttl' => 60000,
                        'x-dead-letter-exchange' => 'dlx',
                    ],
                    'exchange' => 'delayed_exchange',
                ],

                'tour.sync' => [
                    'durable' => true,
                    'exclusive' => false,
                    'auto_delete' => false,
                    'arguments' => [
                        'x-message-ttl' => 60000,
                        'x-dead-letter-exchange' => 'dlx',
                    ],
                    'exchange' => 'delayed_exchange',
                ]
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ]
    ],

    'middleware' => [
        
    ],

    'serializer' => PhpSerializer::class
];