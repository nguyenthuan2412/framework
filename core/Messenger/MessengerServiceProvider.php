<?php

namespace Ryra\Core\Messenger;

use Ryra\Core\Messenger\Serialization\SerializerInterface;
use Ryra\Core\Messenger\Command\ConsumeMessagesCommand;
use Ryra\Core\Messenger\Transport\TransportFactory;
use Ryra\Core\Container\ServiceProvider;
use Ryra\Core\Messenger\Handler\MessageHandlerResolver;
use Ryra\Core\Messenger\Handler\MessageHandlerResolverInterface;

class MessengerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('messenger.serializer', fn ($app) => new ($app['config']->get('messenger.serializer')));
        $this->app->alias('messenger.serializer', SerializerInterface::class);

        $this->app->singleton('messenger.bus', fn ($app) => new MessageBus($app));
        $this->app->alias('messenger.bus', MessageBusInterface::class);

        $this->app->singleton('messenger.transport', fn ($app) => new TransportFactory($app, $app['messenger.serializer']));
        $this->app->alias('messenger.transport', TransportFactory::class);

        $this->app->singleton('messenger.handler.resolver', fn ($app) => new MessageHandlerResolver($app));
        $this->app->alias('messenger.handler.resolver', MessageHandlerResolverInterface::class);
    }

    public function boot(): void
    {
        $this->app->get('console')->register(ConsumeMessagesCommand::class);
        $this->app->get('module.manager')->addExtend('message_handlers', function (array $messageHandlers) {
            foreach ($messageHandlers as $message => $messageHandler) {
                $this->app->get('messenger.handler.resolver')->addHandler($message, $messageHandler);
            }
        });
    }
}