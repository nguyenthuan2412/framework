<?php

namespace Ryra\Core\Server\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Ryra\Core\Process\Facade\Process;
use Ryra\Core\App;
use Ryra\Core\Contracts\Container\Container;
use Ryra\Core\Container\Inject;

#[AsCommand(
    name: 'start:serve',
    description: 'Khởi tạo server application'
)]
class StartServerCommand extends Command
{
    #[Inject()]
    protected Container $app;

    public function __invoke(
        #[Option('Theo dõi sự thay đổi của file và reload lại application.', 'watch', 'W')] bool $watch = false
    ): int
    {
        $driver = config('app.swoole.default');
        $class = config("app.swoole.server.$driver.driver");
        $server = new $class($this->app, config("app.swoole.server.$driver"));
        $this->app->singleton('server', static fn () => $server);

        if ($watch) {
            $watcher = Process::path(join_paths(App::root(), 'core'))
                ->forever()
                ->start('php watcher.php');
        }

        $this->app->get('server')->start();
        return Command::SUCCESS;
    }
}