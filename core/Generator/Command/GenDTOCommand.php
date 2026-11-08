<?php

namespace Ryra\Core\Generator\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Ryra\Core\Generator\GeneratorCommand;

#[AsCommand(name: 'gen:dto', description: 'Generate a new ValidatedDTO file')]
class GenDTOCommand extends GeneratorCommand
{
    protected function stubName(): string   { return 'dto'; }
    protected function subFolder(): string  { return 'DTOs'; }
    protected function fileSuffix(): string { return 'DTO'; }

    protected function stripSuffix(string $name): string
    {
        return preg_match('/dto$/i', $name, $m, PREG_OFFSET_CAPTURE)
            ? substr($name, 0, $m[0][1])
            : $name;
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument(description: 'DTO name, e.g. StoreUser, SearchOrder')] string $name = '',
        #[Option(description: 'The module the DTO belongs to', name: 'module')] string $module = '',
        #[Option(description: 'Overwrite the file if it already exists', name: 'force', shortcut: 'f')] bool $force = false,
    ): int {
        return $this->generate($name, $module, $force, $input, $output);
    }
}
