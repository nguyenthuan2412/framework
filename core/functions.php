<?php

use Ryra\Core\ErrorHandler\Exception\DumperException;
use Symfony\Component\VarDumper\VarDumper;
use Swoole\Coroutine;
use Ryra\Core\App;

require __DIR__ . '/Config/functions.php';
require __DIR__ . '/Collection/functions.php';
require __DIR__ . '/Support/functions.php';
require __DIR__ . '/Route/functions.php';
require __DIR__ . '/Filesystem/functions.php';
require __DIR__ . '/Date/functions.php';
require __DIR__ . '/Messenger/functions.php';
require __DIR__ . '/Translation/functions.php';
require __DIR__ . '/Url/functions.php';
require __DIR__ . '/Asset/functions.php';
require __DIR__ . '/Auth/functions.php';
require __DIR__ . '/Datastar/functions.php';

function env(string $key, mixed $default = null): mixed
{
    $result = $_SERVER[$key] ?? $default;
    if (\is_string($result)) {
        switch (\mb_strtolower($result)) {
            case 'false':
                return false;
            case 'true':
                return true;
            case 'null':
                return null;
        }
    }
    return $result;
}

function dd(mixed ...$vars): never
{
    if (Coroutine::getCid() > -1) {
        throw new DumperException(...$vars);
    } else {
        \dump(...$vars);
        die;
    }
}

function dump(mixed ...$vars): void
{
    foreach ($vars as $k => $v) {
        VarDumper::dump($v, (string) (\is_int($k) ? 1 + $k : $k));
    }
}

function base_path(?string $path = null): string
{
    return !empty($path) ? \join_paths(App::root(), $path) : App::root();
}

function app(?string $service = null): mixed
{
    $app = App::getInstance();
    return \is_null($service) ? $app : $app->get($service);
}

function debounce(callable $fn, int $waitMs): callable
{
    $timerId = null;

    return static function (...$args) use ($fn, $waitMs, &$timerId) {
        if ($timerId !== null) {
            \Swoole\Timer::clear($timerId);
        }

        $timerId = \Swoole\Timer::after($waitMs, static function () use ($fn, &$timerId, $args) {
            $timerId = null; // cleanup
            $fn(...$args);
        });
    };
}