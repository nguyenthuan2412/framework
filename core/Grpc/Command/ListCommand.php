<?php

namespace Ryra\Core\Grpc\Command;

use Ryra\Core\Container\Inject;
use Ryra\Core\Grpc\Server\ServiceRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'grpc:list', description: 'Liệt kê các rpc đã đăng ký')]
class ListCommand extends Command
{
    #[Inject('grpc.registry')]
    protected ServiceRegistry $registry;

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];
        foreach ($this->registry->all() as $service) {
            foreach ($service->methods as $method) {
                $type = match (true) {
                    $method->clientStreaming && $method->serverStreaming => 'bidi',
                    $method->clientStreaming => 'client stream',
                    $method->serverStreaming => 'server stream',
                    default => 'unary',
                };
                $rows[] = [$service->path($method), $type, $service->class . '::' . $method->phpMethod];
            }
        }
        (new SymfonyStyle($input, $output))->table(['Path', 'Type', 'Handler'], $rows);
        return Command::SUCCESS;
    }
}
