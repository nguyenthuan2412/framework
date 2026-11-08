<?php

namespace Ryra\Core\Snowflake\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static string id()
 * @method static array parseId(string $id, bool $transform = false)
 * @method static int getCurrentMillisecond()
 * @method static self setStartTimeStamp(int $millisecond)
 * @method static float|int getStartTimeStamp()
 * @method static self setSequenceResolver(\Closure|\Godruoyi\Snowflake\SequenceResolver $sequence)
 * @method static null|\Closure|\Godruoyi\Snowflake\SequenceResolver getSequenceResolver()
 * @method static \Godruoyi\Snowflake\SequenceResolver getDefaultSequenceResolver()
 */
class Snowflake extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'snowflake';
    }
}