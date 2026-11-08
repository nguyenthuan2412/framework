<?php

use Ryra\Core\Watcher\Drivers\Polling;
use Ryra\Core\Watcher\Watcher;
use Ryra\Core\Watcher\Event;
use Ryra\Core\App;

$basePath = \dirname(__FILE__, 2);

require "$basePath/core/constants.php";
require "$basePath/core/functions.php";
require "$basePath/vendor/autoload.php";

App::getInstance()->setBasePath($basePath);

$config = require "$basePath/config/watcher.php";

$watcher = Watcher::create((bool) $config['use_polling']);
if ($watcher instanceof Polling) {
    $interval = !empty($config['interval']) ? $config['interval'] : 300;
    $watcher->setTimeInterval($interval);
}

foreach ($config['paths'] as $item) {
    $regex = \join('|', \array_map(static fn ($regex) => "($regex)", $item['regex'] ?? []));
    $watcher->add($item['path'], !empty($regex) ? $regex : null);
}

$restartServer = \debounce(static fn () => \posix_kill(posix_getppid(), SIGUSR1), 500);
$allowMethods = [Event::CHANGE, Event::ADD, Event::ADD_DIR, Event::UNLINK];
$watcher->on($allowMethods, $restartServer)->run();