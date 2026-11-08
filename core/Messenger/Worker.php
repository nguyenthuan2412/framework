<?php

namespace Ryra\Core\Messenger;

use Ryra\Core\Messenger\Handler\MessageHandlerResolverInterface;
use Ryra\Core\Messenger\Transport\TransportInterface;
use Ryra\Core\Coroutine\Timer;

class Worker
{
    public function __construct(
        private TransportInterface $transport,
        private MessageHandlerResolverInterface $handlerResolver
    ) {}

    public function run()
    {
        Timer::interval(function () {
            try {
                $envelop = $this->transport->get();
                if ($envelop = $this->transport->get()) {
                    $body = $envelop->getBody();
                    if ($handler = $this->handlerResolver->resolve($body)) {
                        $handler($body);
                        $this->transport->ack($envelop);
                    }
                }
            } catch (\Throwable $th) {
                if (!empty($envelop)) {
                    $this->transport->reject($envelop);
                }

                dump($th->getMessage());
            }
        }, 1000);
    }
}