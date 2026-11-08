<?php

namespace Ryra\Core\Console;

use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Command\Command;
use Ryra\Core\App;

class Application extends ConsoleApplication
{
    public function __construct(
        protected App $app,
        protected InputInterface $input,
        protected OutputInterface $output
    ) {
        parent::__construct($app->getCoreVersion(), $app->getCoreName());
    }

    /**
     * Chạy application hiện tại.
     * 
     * @return int Nếu không có lỗi sẽ return 0 và ngược lại sẽ return 1 error code.
     */
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        return parent::run(
            $this->input,
            $this->output
        );
    }

    public function register(string $name): Command
    {
        if (class_exists($name)) {
            return $this->addCommand(App::make($name));
        }

        return $this->addCommand(new Command($name));
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output): int
    {
        $defaultCommands = [
            'list', 
            'help', 
            'about'
        ];
        if (in_array($command->getName(), $defaultCommands)) {
$logo = '
██████╗ ██╗   ██╗██████╗  █████╗ 
██╔══██╗╚██╗ ██╔╝██╔══██╗██╔══██╗
██████╔╝ ╚████╔╝ ██████╔╝███████║
██╔══██╗  ╚██╔╝  ██╔══██╗██╔══██║
██║  ██║   ██║   ██║  ██║██║  ██║
╚═╝  ╚═╝   ╚═╝   ╚═╝  ╚═╝╚═╝  ╚═╝
';

            $output->writeln($logo);
        }
        return parent::doRunCommand($command, $input, $output);
    }
}