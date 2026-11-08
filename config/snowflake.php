<?php

use Godruoyi\Snowflake\SwooleSequenceResolver;
use Ryra\Core\Date\Date;

return [
    'resolver' => SwooleSequenceResolver::class,

    'datacenter' => -1,

    'start_time_stamp' => Date::parse('2025-01-01 00:00:00')->getTimestamp(),
];