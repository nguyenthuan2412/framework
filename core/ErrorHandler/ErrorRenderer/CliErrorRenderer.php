<?php

namespace Ryra\Core\ErrorHandler\ErrorRenderer;

use Symfony\Component\VarDumper\VarDumper;
use Ryra\Core\App;
use Ryra\Core\ErrorHandler\Exception\DumperException;
use Ryra\Core\Log\Log;

class CliErrorRenderer
{
    public static function render(\Throwable $throwable): void
    {
        if ($throwable instanceof DumperException) {
            foreach ($throwable->vars as $k => $v) {
                VarDumper::dump($v, is_int($k) ? 1 + $k : $k);
            }
            return;
        }

        Log::error($throwable);
        if (App::isProduction()) {
            return;
        }

        if ($throwable instanceof \ErrorException) {
            $errtype = match ($throwable->getSeverity()) {
                E_ERROR             => 'Fatal Error',
                E_WARNING           => 'Warning',
                E_PARSE             => 'Parse Error',
                E_NOTICE            => 'Notice',
                E_CORE_ERROR        => 'Core Error',
                E_CORE_WARNING      => 'Core Warning',
                E_COMPILE_ERROR     => 'Compile Error',
                E_COMPILE_WARNING   => 'Compile Warning',
                E_USER_ERROR        => 'Custom Error',
                E_USER_WARNING      => 'Custom Warning',
                E_USER_NOTICE       => 'Custom Notice',
                E_STRICT            => 'Strict Notice',
                E_RECOVERABLE_ERROR => 'Recoverable Error',
                E_DEPRECATED        => 'Deprecated Warning',
                E_USER_DEPRECATED   => 'User Deprecated Warning',
                default             => 'Unknown Error'
            };
        } else {
            $errtype = get_class($throwable);
        }

        $contentError = "";
        $data = file($throwable->getFile());
        $totalLines = count($data);
        $errline = $throwable->getLine();
        $startLine = $errline >= 5 ? $errline - 5 : 1;
        $endLine = $totalLines - $errline >= 5 ? $errline + 5 : $totalLines;
        for ($line = $startLine; $line <= $endLine; $line++) {
            if ($line == $errline) {
                $strline = str_pad($line, strlen($endLine), ' ', STR_PAD_LEFT);
                $lineContent = str_replace(' ', '&nbsp;', $data[$line - 1]);
                $contentError .= "<div class='text-red-500'>≫&nbsp;&nbsp;$strline|$lineContent</div>";
            } else {
                $strline = str_pad($line, strlen($endLine), ' ', STR_PAD_LEFT);
                $lineContent = str_replace(' ', '&nbsp;', $data[$line - 1]);
                $contentError .= "<div>&nbsp;&nbsp;&nbsp;$strline|$lineContent</div>";
            }
        }
        $backtrace = array_slice($throwable->getTrace(), 0, 3);
        $traceHtml = null;
        if (!empty($backtrace)) {
            $traceHtml = <<<HTML
                <div>&nbsp;&nbsp;&nbsp;Exception trace:</div>
            HTML;
            foreach ($backtrace as $index => $trace) {
                $index += 1;
                if (!empty($trace['class'])) {
                    $func = "{$trace['class']}::{$trace['function']}";
                } else {
                    $func = $trace['function'];
                }
                $traceHtml .= <<<HTML
                    <div class="mt-1 text-amber-300">
                        <span class="ml-3 mr-3 text-teal-600">$index</span>
                        $func
                    </div>
                HTML;
                if (!empty($trace['file']) && !empty($trace['line'])) {
                    $traceHtml .= <<<HTML
                        <div class="pl-8 text-blue-300">{$trace['file']}:{$trace['line']}</div>
                    HTML;
                }
            }
        }

        \Termwind\render(<<<HTML
            <div class="mb-1">
                <div class="font-bold bg-red-700 mb-1">$errtype</div>
                <div class="font-bold mb-1">{$throwable->getMessage()}</div>
                <div>at <span class="text-lime-500">{$throwable->getFile()}:{$errline}</span></div>
                <div class="text-gray-200 mb-1">$contentError</div>
                $traceHtml
            </div>
        HTML);
    }
}