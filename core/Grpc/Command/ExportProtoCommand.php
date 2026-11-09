<?php

namespace Ryra\Core\Grpc\Command;

use Ryra\Core\Container\Inject;
use Ryra\Core\Grpc\ProtoGenerator;
use Ryra\Core\Grpc\Server\ServiceRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'grpc:proto',
    description: 'Xuất file .proto từ các class #[Service]/#[Message] để chia sẻ cho service khác'
)]
class ExportProtoCommand extends Command
{
    #[Inject('grpc.registry')]
    protected ServiceRegistry $registry;

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option('Thư mục xuất file .proto', 'output', 'o')] string $path = '',
        #[Option('Chỉ in ra màn hình, không ghi file', 'dry-run')] bool $dryRun = false,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $path = $path !== '' ? $path : (string) config('grpc.proto.output', base_path('proto'));

        try {
            $files = (new ProtoGenerator((array) config('grpc.proto.options', [])))
                ->generate(\array_values($this->registry->all(true)), (array) config('grpc.messages', []));
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        if ($files === []) {
            $io->warning('No gRPC service registered. Add classes to `grpc_services` in your *.module.php or config/grpc.php.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            foreach ($files as $name => $content) {
                $io->section($name);
                $output->writeln($content);
            }
            return Command::SUCCESS;
        }

        if (!\is_dir($path)) {
            \mkdir($path, 0775, true);
        }
        foreach ($files as $name => $content) {
            \file_put_contents(\rtrim($path, '/') . '/' . $name, $content);
        }

        $io->listing(\array_keys($files));
        $io->success('Exported ' . \count($files) . " proto file(s) to $path");
        return Command::SUCCESS;
    }
}
