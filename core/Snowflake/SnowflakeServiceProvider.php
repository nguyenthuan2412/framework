<?php

namespace Ryra\Core\Snowflake;

use Godruoyi\Snowflake\Snowflake;
use Ryra\Core\Container\ServiceProvider;

class SnowflakeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('snowflake', function () {
            $config = config('snowflake');
            if (!empty($server = $this->app->get('server'))) {
                $workerId = $server->workerId();
            } else {
                $workerId = getmypid() % 1024;
            }

            $snowflake = new Snowflake($config['datacenter'], $workerId);
            $resolver = $config['resolver'];
            $snowflake->setStartTimeStamp($config['start_time_stamp'] * 1000);
            $snowflake->setSequenceResolver(new $resolver);
            return $snowflake;
        });
    }
}