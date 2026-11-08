<?php

namespace Ryra\Core\Generator\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Ryra\Core\Generator\GeneratorCommand;

#[AsCommand(name: 'gen:controller', description: 'Generate a new Controller file')]
class GenControllerCommand extends GeneratorCommand
{
    protected function stubName(): string     { return 'controller'; }
    protected function subFolder(): string    { return 'Controllers'; }
    protected function fileSuffix(): string   { return 'Controller'; }
    protected function moduleSection(): string { return 'controllers'; }

    protected function stripSuffix(string $name): string
    {
        return preg_match('/c[ao]nt?r[ao]l{1,2}e?r?$/i', $name, $m, PREG_OFFSET_CAPTURE)
            ? substr($name, 0, $m[0][1])
            : $name;
    }

    protected function extraReplacements(string $baseName, string $moduleClass, string $module): array
    {
        return ['prefix' => 'api/' . $this->toPlural(strtolower($baseName))];
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument(description: 'Controller name, e.g. User, agent-tour')] string $name = '',
        #[Option(description: 'The module the controller belongs to', name: 'module')] string $module = '',
        #[Option(description: 'Overwrite the file if it already exists', name: 'force', shortcut: 'f')] bool $force = false,
    ): int {
        return $this->generate($name, $module, $force, $input, $output);
    }
}
