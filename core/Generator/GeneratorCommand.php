<?php

namespace Ryra\Core\Generator;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Command\Command;
use Ryra\Core\Module\ModuleManager;
use Ryra\Core\Container\Inject;
use Ryra\Core\App;

abstract class GeneratorCommand extends Command
{
    #[Inject]
    protected ModuleManager $modules;

    abstract protected function stubName(): string;
    abstract protected function subFolder(): string;
    abstract protected function fileSuffix(): string;

    protected function stripSuffix(string $name): string
    {
        return $name;
    }

    protected function extraReplacements(string $_baseName, string $_moduleClass, string $_module): array
    {
        return [];
    }

    protected function moduleSection(): ?string
    {
        return null;
    }

    protected function generate(string $name, string $module, bool $force, InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title(ucwords(str_replace(':', ' ', $this->getName())));

        if (empty($name)) {
            $io->error('Name is required. Usage: php vietiso ' . $this->getName() . ' <name> --module=<module>');
            return self::FAILURE;
        }

        if (empty($module)) {
            $io->error('Module is required. Use --module=<module> to specify the target module.');
            return self::FAILURE;
        }

        if (!$this->modules->has($module)) {
            $io->error("Module \"$module\" does not exist.");
            return self::FAILURE;
        }

        $baseName    = $this->toPascalCase($this->stripSuffix($name));
        $moduleClass = $this->toPascalCase($module);
        $namespace   = "Ryra\\Modules\\{$moduleClass}\\" . $this->subFolder();
        $fileName    = $baseName . $this->fileSuffix() . '.php';
        $path        = join_paths(App::root(), 'modules', $module, 'src', $this->subFolder(), $fileName);

        $replacements = array_merge([
            'namespace' => $namespace,
            'name'      => $baseName,
            'module'    => $moduleClass,
        ], $this->extraReplacements($baseName, $moduleClass, $module));

        $io->table(
            ['Option', 'Value'],
            [
                ['Class',   $baseName . $this->fileSuffix()],
                ['Module',  $moduleClass],
                ['Namespace', $namespace],
                ['Output path', $path],
            ]
        );

        $content = $this->applyReplacements($this->loadStub($this->stubName()), $replacements);
        $result  = $this->writeFile($path, $content, $force, $io);

        if ($result === Command::SUCCESS && $this->moduleSection() !== null) {
            $moduleFile = join_paths(App::root(), 'modules', $module, $module . '.module.php');
            $fqcn       = $namespace . '\\' . $baseName . $this->fileSuffix();
            $editor     = new ModuleFileEditor();
            if ($editor->addEntry($moduleFile, $this->moduleSection(), $fqcn)) {
                $io->success("Registered in " . basename($moduleFile));
            } else {
                $io->warning("Could not auto-register in {$module}.module.php — add manually.");
            }
        }

        return $result;
    }

    protected function loadStub(string $name): string
    {
        return file_get_contents(__DIR__ . '/Stubs/' . $name . '.stub');
    }

    protected function applyReplacements(string $stub, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $stub = str_replace('{{' . $key . '}}', $value, $stub);
        }
        return $stub;
    }

    protected function writeFile(string $path, string $content, bool $force, SymfonyStyle $io): int
    {
        if (file_exists($path) && !$force) {
            $io->error([
                "File already exists: $path",
                'Use --force to overwrite.',
            ]);
            return Command::FAILURE;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $content);
        $io->success("Created: $path");
        return Command::SUCCESS;
    }

    protected function toPascalCase(string $value): string
    {
        return implode('', array_map(fn($w) => ucfirst(strtolower($w)), preg_split('/[-_\s]+/', $value)));
    }

    protected function toPlural(string $value): string
    {
        if (str_ends_with($value, 's')) {
            return $value;
        }
        if (str_ends_with($value, 'y')) {
            return substr($value, 0, -1) . 'ies';
        }
        return $value . 's';
    }
}
