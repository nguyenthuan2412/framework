<?php

namespace Ryra\Core\ErrorHandler\ErrorRenderer;

use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Ryra\Core\ErrorHandler\Exception\DumperException;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Ryra\Core\Http\Response;
use Throwable;

class HtmlErrorRenderer
{
    protected static $dumper = null;

    protected static $whoops = null;

    public static function render(Throwable $exception): Response
    {
        if ($exception instanceof DumperException) {
            if (is_null(static::$dumper)) {
                $dumper = new HtmlDumper();
            } else {
                $dumper = static::$dumper;
            }

            return Response::stream(static function () use ($exception, $dumper) {
                foreach ($exception->vars as $var) {
                    $cloner = new VarCloner();
                    $cloner->addCasters(ReflectionCaster::UNSET_CLOSURE_FILE_INFO);
                    $var = $cloner->cloneVar(var: $var);
                    ob_start();
                    $dumper->dump($var);
                    yield ob_get_clean();
                }
            })->setStatusCode(500);
        }

        if (is_null(static::$whoops)) {
            $whoops = new \Whoops\Run;
            $whoops->allowQuit(false);
            $whoops->writeToOutput(false);
            $handler = new \Whoops\Handler\PrettyPageHandler();
            $handler->handleUnconditionally(true);
            $whoops->pushHandler($handler);
            static::$whoops = $whoops;
        } else {
            $whoops = static::$whoops;
        }

        $html = $whoops->handleException($exception);
        return Response::html($html)
            ->setStatusCode(500);
    }
}