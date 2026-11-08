<?php

namespace Ryra\Core\Generator\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Ryra\Core\Generator\GeneratorCommand;

#[AsCommand(name: 'gen:model', description: 'Generate a new Model file')]
class GenModelCommand extends GeneratorCommand
{
    protected function stubName(): string   { return 'model'; }
    protected function subFolder(): string  { return 'Models'; }
    protected function fileSuffix(): string { return ''; }

    protected function stripSuffix(string $name): string
    {
        return preg_match('/model$/i', $name, $m, PREG_OFFSET_CAPTURE)
            ? substr($name, 0, $m[0][1])
            : $name;
    }

    protected function extraReplacements(string $baseName, string $moduleClass, string $module): array
    {
        return ['table' => $this->toPlural(strtolower($baseName))];
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument(description: 'Model name, e.g. User, agent-tour')] string $name = '',
        #[Option(description: 'The module the model belongs to', name: 'module')] string $module = '',
        #[Option(description: 'Overwrite the file if it already exists', name: 'force', shortcut: 'f')] bool $force = false,
    ): int {
        return $this->generate($name, $module, $force, $input, $output);
    }
}
