<?php

namespace Ryra\Core\Generator\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Ryra\Core\App;

#[AsCommand(name: 'gen:module', description: 'Scaffold a new module')]
class GenModuleCommand extends Command
{
    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument(description: 'Module name, e.g. tour, booking, new_feature')] string $name = '',
        #[Option(description: 'Display name shown in module config', name: 'display')] string $display = '',
        #[Option(description: 'Overwrite if module already exists', name: 'force', shortcut: 'f')] bool $force = false,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $io->title('Gen Module');

        if (empty($name)) {
            $io->error('Name is required. Usage: php vietiso gen:module <name>');
            return self::FAILURE;
        }

        $moduleKey   = strtolower($name);
        $displayName = $display ?: $moduleKey;
        $root        = join_paths(App::root(), 'modules', $moduleKey);
        $moduleFile  = join_paths($root, "$moduleKey.module.php");

        if (file_exists($moduleFile) && !$force) {
            $io->error(["Module \"$moduleKey\" already exists.", 'Use --force to overwrite.']);
            return self::FAILURE;
        }

        if (!is_dir($root)) {
            mkdir($root, 0755, true);
        }

        file_put_contents($moduleFile, $this->applyReplacements($this->loadStub('module'), [
            'displayName' => $displayName,
        ]));

        $io->success("Created: $moduleFile");
        return self::SUCCESS;
    }

    private function loadStub(string $name): string
    {
        return file_get_contents(__DIR__ . '/../Stubs/' . $name . '.stub');
    }

    private function applyReplacements(string $stub, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $stub = str_replace('{{' . $key . '}}', $value, $stub);
        }
        return $stub;
    }

}
