<?php

namespace Ryra\Core\Log;

use Monolog\Logger;
use Ryra\Core\App;
use Ryra\Core\Support\Facade;

/**
 * @method static void error(string|\Stringable $message, array $context = [])
 * @method static void info(string|\Stringable $message, array $context = [])
 * @method static void debug(string|\Stringable $message, array $context = [])
 * @method static void warning(string|\Stringable $message, array $context = [])
 * @method static void critical(string|\Stringable $message, array $context = [])
 * @method static void alert(string|\Stringable $message, array $context = [])
 * @method static void emergency(string|\Stringable $message, array $context = [])
 * @method static void notice(string|\Stringable $message, array $context = [])
 * @method static void log(string $level, string|\Stringable $message, array $context = [])
 */
class Log extends Facade
{
    /**
     * Detailed debug information
     */
    public const DEBUG = \Monolog\Level::Debug->value;

    /**
     * Interesting events
     *
     * Examples: User logs in, SQL logs.
     */
    public const INFO = \Monolog\Level::Info->value;

    /**
     * Uncommon events
     */
    public const NOTICE = \Monolog\Level::Notice->value;

    /**
     * Exceptional occurrences that are not errors
     *
     * Examples: Use of deprecated APIs, poor use of an API,
     * undesirable things that are not necessarily wrong.
     */
    public const WARNING = \Monolog\Level::Warning->value;

    /**
     * Runtime errors
     */
    public const ERROR = \Monolog\Level::Error->value;

    /**
     * Critical conditions
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @deprecated Use \Monolog\Level::Critical
     */
    public const CRITICAL = \Monolog\Level::Critical->value;

    /**
     * Action must be taken immediately
     *
     * Example: Entire website down, database unavailable, etc.
     * This should trigger the SMS alerts and wake you up.
     */
    public const ALERT = \Monolog\Level::Alert->value;

    /**
     * Urgent alert.
     *
     * @deprecated Use \Monolog\Level::Emergency
     */
    public const EMERGENCY = \Monolog\Level::Emergency->value;

    public static function get($name = 'vietiso', $group = 'default'): Logger
    {
        return App::service('log')->get($name, $group);
    }

    protected static function getFacadeAccessor(): string|object
    {
        return App::service('log')->get();
    }
}