<?php

namespace Ryra\Core\Messenger\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Ryra\Core\Messenger\Handler\MessageHandlerResolverInterface;
use Ryra\Core\Messenger\Transport\TransportFactory;
use Ryra\Core\Contracts\Config\Repository;
use Ryra\Core\Messenger\Worker;
use Ryra\Core\Container\Inject;

#[AsCommand(
    name: 'messenger:consume',
    description: 'Lấy và xử lý message từ queue'
)]
class ConsumeMessagesCommand extends Command
{
    #[Inject()]
    private Repository $config;

    #[Inject()]
    private TransportFactory $transportFactory;

    #[Inject()]
    private MessageHandlerResolverInterface $handlerResolver;

    public function __invoke(): int
    {
        $connection = $this->config->get('messenger.default');
        $transport = $this->transportFactory->createTransport($connection);
        (new Worker($transport, $this->handlerResolver))->run();
        return Command::SUCCESS;
    }
}