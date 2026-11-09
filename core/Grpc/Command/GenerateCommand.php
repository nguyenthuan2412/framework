<?php

namespace Ryra\Core\Grpc\Command;

use Ryra\Core\Container\Inject;
use Ryra\Core\Grpc\ProtoCompiler;
use Ryra\Core\Grpc\Server\ServiceRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'grpc:generate',
    description: 'Sinh .proto từ các class #[Service]/#[Message] và biên dịch bằng protoc (google/protobuf)'
)]
class GenerateCommand extends Command
{
    #[Inject('grpc.registry')]
    protected ServiceRegistry $registry;

    #[Inject('grpc.compiler')]
    protected ProtoCompiler $compiler;

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('gRPC generate');

        try {
            $result = $this->compiler->compile(\array_values($this->registry->all()), (array) config('grpc.messages', []));
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->listing(\array_map('basename', $result['proto']));
        $io->success(\sprintf(
            'Compiled %d proto file(s), mapped %d message class(es) into %s',
            \count($result['proto']),
            $result['classes'],
            $this->compiler->phpPath(),
        ));
        $io->note('Restart the server to load the new classes.');

        return Command::SUCCESS;
    }
}
