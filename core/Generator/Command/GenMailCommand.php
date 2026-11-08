<?php

namespace Ryra\Core\Generator\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Ryra\Core\Generator\GeneratorCommand;

#[AsCommand(name: 'gen:mail', description: 'Generate a new Mailable file')]
class GenMailCommand extends GeneratorCommand
{
    protected function stubName(): string   { return 'mail'; }
    protected function subFolder(): string  { return 'Mails'; }
    protected function fileSuffix(): string { return ''; }

    protected function extraReplacements(string $baseName, string $moduleClass, string $module): array
    {
        return ['module_lower' => strtolower($module)];
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument(description: 'Mail name, e.g. WelcomeUser, BookingConfirmation')] string $name = '',
        #[Option(description: 'The module the mail belongs to', name: 'module')] string $module = '',
        #[Option(description: 'Overwrite the file if it already exists', name: 'force', shortcut: 'f')] bool $force = false,
    ): int {
        return $this->generate($name, $module, $force, $input, $output);
    }
}
