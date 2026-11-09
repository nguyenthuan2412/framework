<?php

namespace Ryra\Core\Grpc\Command;

use Ryra\Core\Grpc\Proto\DtoGenerator;
use Ryra\Core\Support\Str;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'grpc:dto',
    description: 'Sinh DTO #[Message], enum và interface #[Service] từ file .proto (vd .proto của service khác)'
)]
class DtoCommand extends Command
{
    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('File .proto (các file import sẽ được sinh kèm)')] array $files,
        #[Option('Module chứa class sinh ra, vd Billing => Ryra\Modules\Billing\Grpc', 'module', 'm')] string $module = '',
        #[Option('Namespace gốc (thay cho --module)', 'namespace')] string $namespace = '',
        #[Option('Thư mục ghi file (thay cho --module)', 'output', 'o')] string $path = '',
        #[Option('Thư mục tìm file import', 'include', 'I')] array $include = [],
        #[Option('Chỉ in ra màn hình, không ghi file', 'dry-run')] bool $dryRun = false,
    ): int {
        $io = new SymfonyStyle($input, $output);

        if ($module !== '') {
            $module = Str::studly($module);
            $namespace = $namespace !== '' ? $namespace : "Ryra\\Modules\\$module\\Grpc";
            $path = $path !== '' ? $path : base_path('modules/' . Str::snake($module) . '/src/Grpc');
        }
        if ($namespace === '' || $path === '') {
            $io->error('Cần --module=<Tên module>, hoặc cả --namespace và --output.');
            return Command::FAILURE;
        }

        try {
            $generated = (new DtoGenerator(
                $namespace,
                [...$include, ...(array) config('grpc.dto.include_paths', []), base_path()],
            ))->generate($files);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        if ($dryRun) {
            foreach ($generated as $file => $content) {
                $io->section($file);
                $output->writeln($content);
            }
            return Command::SUCCESS;
        }

        foreach ($generated as $file => $content) {
            $target = \rtrim($path, '/') . '/' . $file;
            if (!\is_dir(\dirname($target))) {
                \mkdir(\dirname($target), 0775, true);
            }
            \file_put_contents($target, $content);
        }

        $io->listing(\array_keys($generated));
        $io->success(\count($generated) . " file(s) generated in $path ($namespace)");
        return Command::SUCCESS;
    }
}
