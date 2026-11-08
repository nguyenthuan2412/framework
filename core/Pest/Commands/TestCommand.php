<?php

namespace Ryra\Core\Pest\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Ryra\Core\Module\ModuleManager;
use Ryra\Core\Container\Inject;
use Pest\TestSuite;
use Pest\Kernel;
use Pest\Panic;
use Ryra\Core\App;

#[AsCommand(
    name: 'test',
    description: 'Run the application test suite'
)]
class TestCommand extends Command
{
    #[Inject]
    protected ModuleManager $modules;

    #[Inject]
    protected App $app;

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option('Run only the tests belonging to the given module.', 'module', 'M')] string $module = ''
    ): int
    {
        $module = $this->modules->get($module);
        if (!$module) {
            $output->writeln('Module not found');
            return Command::FAILURE;
        }

        $folderTest = join_paths($module->getPath(), 'tests');
        $arguments = ['pest', $folderTest];

        $testSuite = TestSuite::getInstance($this->app->root(), $folderTest);
        $isDecorated = $input->getParameterOption('--colors', 'always') !== 'never';
        $output = new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, $isDecorated);

        try {
            $kernel = Kernel::boot($testSuite, $input, $output);
            $kernel->handle($arguments, $arguments);
            $kernel->terminate();
            return Command::SUCCESS;
        } catch (\Throwable|\Error $e) {
            Panic::with($e);
            return Command::FAILURE;
        }
    }
}